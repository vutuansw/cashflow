<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Camera, History, ImagePlus, ReceiptText, RotateCcw, ScanText, Trash2, X } from 'lucide-vue-next';
import AdminShell from '../../layouts/AdminShell.vue';
import CategoryIcon from '../../components/CategoryIcon.vue';
import FormField from '../../components/FormField.vue';
import MoneyInput from '../../components/MoneyInput.vue';
import PhotoViewer from '../../components/PhotoViewer.vue';
import { adminApi } from '../../lib/api.js';
import { formatMoney } from '../../lib/money.js';
import { useForm, ignore } from '../../lib/form.js';
import { previewUrl, shrinkImage } from '../../lib/image.js';
import { formatDate, formatDateTime } from '../../lib/time.js';
import { fold } from '../../lib/text.js';
import { toast } from '../../lib/notice.js';
import { readBill } from '../../lib/billScan.js';

// Tạo/sửa phiếu thu chi, xem plan/03 mục 2 và wireframe M2 màn 2. Có ảnh bill thì AI đọc và điền sẵn (plan/04).
const route = useRoute();
const router = useRouter();
const code = computed(() => route.params.group);
const id = computed(() => (route.params.id ? Number(route.params.id) : null));
const MAX_PHOTOS = 10;

const options = ref({ categories: [], campaigns: [], contacts: [] });
const detail = ref(null);
const ready = ref(false);
const { state, submit } = useForm();

const pad = (n) => String(n).padStart(2, '0');
const now = new Date();
const form = reactive({
    type: route.query.loai === 'thu' ? 'income' : 'expense',
    amount: null,
    category_id: null,
    date: `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`,
    time: `${pad(now.getHours())}:${pad(now.getMinutes())}`,
    contact_id: null,
    payer_name: '',
    campaign_id: null,
    title: '',
    description: '',
    show_on_timeline: true,
});
const titleTouched = ref(false);

// Ảnh: ảnh cũ (từ máy chủ) và ảnh mới chọn (File + URL xem trước).
const photos = ref([]); // [{ id?, url, file? }]
const removedPhotoIds = ref([]);
const bills = ref([]); // [{ id?, scanId?, url, file? }]
const removedBillIds = ref([]);
const viewing = ref(null);

const categories = computed(() => options.value.categories.filter((c) => c.type === form.type));
const category = computed(() => options.value.categories.find((c) => c.id === form.category_id));
const isIncome = computed(() => form.type === 'income');
const deleted = computed(() => Boolean(detail.value?.deleted_at));

// Người đóng / người nhận: gõ tên, gợi ý từ danh bạ (tìm không dấu); không chọn thì lưu tên tự do.
const personQuery = ref('');
const personOpen = ref(false);
const personMatches = computed(() => {
    const q = fold(personQuery.value);
    if (!q) return options.value.contacts.slice(0, 8);
    return options.value.contacts.filter((c) => c.search.includes(q)).slice(0, 8);
});
const personName = computed(() => options.value.contacts.find((c) => c.id === form.contact_id)?.name ?? form.payer_name.trim());

function pickContact(c) {
    form.contact_id = c.id;
    form.payer_name = '';
    personQuery.value = c.name;
    personOpen.value = false;
    candidates.value = [];
}
function onPersonInput(value) {
    personQuery.value = value;
    form.contact_id = null;
    form.payer_name = value;
    personOpen.value = true;
}
function closePerson() {
    setTimeout(() => (personOpen.value = false), 150);
}

// Tiêu đề tự gợi ý "{Loại} {tên}" cho tới khi người dùng tự sửa.
const suggestedTitle = computed(() => [category.value?.name, personName.value].filter(Boolean).join(' '));
watch(suggestedTitle, (v) => {
    if (!titleTouched.value) form.title = v;
});

// Phiếu thu nhập tay mặc định gắn chiến dịch đang mở mới nhất (danh sách đã xếp mới nhất trước).
// Phiếu từ bill/SePay thì giữ đúng chiến dịch nhận ra được, không tự gán.
const newestCampaignId = () => options.value.campaigns[0]?.id ?? null;
const autoCampaign = () => !id.value && !scan.value && !sepay.value && !claim.value;

watch(() => form.type, () => {
    if (!categories.value.some((c) => c.id === form.category_id)) form.category_id = categories.value[0]?.id ?? null;
    if (!isIncome.value) form.campaign_id = null;
    else if (form.campaign_id === null && autoCampaign()) form.campaign_id = newestCampaignId();
});
watch(() => form.campaign_id, (v) => {
    const campaign = options.value.campaigns.find((c) => c.id === v);
    if (campaign) {
        form.category_id = campaign.category_id;
        if (!form.amount && campaign.amount_per_member) form.amount = campaign.amount_per_member;
    }
});

// AI đọc bill: lượt đọc hiện tại, các ô AI điền (hiện nhãn "AI đọc" tới khi người dùng sửa), người có thể là.
const scan = ref(null);
const sepay = ref(null); // giao dịch SePay đang duyệt qua form (plan/05 mục 4)
const claim = ref(null); // bill thành viên gửi đang duyệt qua form (plan/05 mục 5)
const aiFields = ref([]);
const candidates = ref([]);
const isAi = (key) => aiFields.value.includes(key);
const aiTag = computed(() => (sepay.value ? 'Tự điền' : 'AI đọc'));
const dropAi = (key) => (aiFields.value = aiFields.value.filter((k) => k !== key));
let stopAiWatchers = [];

async function applyScan(s) {
    scan.value = s;
    if (s.id && s.bill_url && !bills.value.some((b) => b.scanId === s.id)) bills.value.push({ scanId: s.id, url: s.bill_url, lg: s.bill_url });
    const d = s.draft;
    if (!d || s.attached) return;

    stopAiWatchers.forEach((stop) => stop());
    if (d.type) form.type = d.type;
    if (d.amount) form.amount = d.amount;
    if (d.occurred_at) [form.date, form.time] = d.occurred_at.split(' ');
    form.campaign_id = d.campaign_id ?? null;
    if (d.category_id) form.category_id = d.category_id;
    if (d.contact_id) {
        const c = options.value.contacts.find((x) => x.id === d.contact_id);
        form.contact_id = d.contact_id;
        form.payer_name = '';
        personQuery.value = c?.name ?? '';
    } else if (d.payer_name) {
        form.contact_id = null;
        form.payer_name = d.payer_name;
        personQuery.value = d.payer_name;
    }
    candidates.value = d.contact_candidates ?? [];
    if (d.description && !form.description) form.description = d.description;
    titleTouched.value = false;

    const map = { occurred_at: 'date', contact_id: 'person', payer_name: 'person', campaign_id: 'campaign_id' };
    aiFields.value = [...new Set((d.ai_fields ?? []).map((k) => map[k] ?? k))];

    // Đợi các watcher (loại theo thu/chi, theo chiến dịch) chạy xong rồi mới theo dõi người dùng sửa.
    await nextTick();
    stopAiWatchers = [
        watch(() => form.type, () => dropAi('type')),
        watch(() => form.amount, () => dropAi('amount')),
        watch(() => [form.date, form.time], () => dropAi('date')),
        watch(() => form.category_id, () => dropAi('category_id')),
        watch(() => form.campaign_id, () => dropAi('campaign_id')),
        watch(personQuery, () => dropAi('person')),
    ];
}

async function onBill(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    try {
        const s = await readBill(code.value, file);
        await applyScan(s);
        router.replace({ query: { ...route.query, scan: s.id } });
        toast(s.message, s.readable ? 'success' : 'error');
    } catch (e) {
        toast(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'Không tải được ảnh bill.', 'error');
    }
}

onMounted(async () => {
    options.value = await adminApi(code.value, '/transactions/options');
    if (id.value) {
        const d = await adminApi(code.value, `/transactions/${id.value}`);
        detail.value = d;
        const at = new Date(d.occurred_at);
        Object.assign(form, {
            type: d.type,
            amount: d.amount,
            category_id: d.category_id,
            date: `${at.getFullYear()}-${pad(at.getMonth() + 1)}-${pad(at.getDate())}`,
            time: `${pad(at.getHours())}:${pad(at.getMinutes())}`,
            contact_id: d.contact_id,
            payer_name: d.payer_name ?? '',
            campaign_id: d.campaign_id,
            title: d.title,
            description: d.description ?? '',
            show_on_timeline: d.show_on_timeline,
        });
        // Loại đã ẩn vẫn phải chọn được cho phiếu cũ.
        if (d.category && !options.value.categories.some((c) => c.id === d.category_id)) {
            options.value.categories.push({ ...d.category, type: d.type });
        }
        personQuery.value = d.person ?? '';
        titleTouched.value = true;
        photos.value = d.photos.map((p) => ({ id: p.id, url: p.sm, lg: p.lg }));
        bills.value = d.bills.map((b) => ({ id: b.id, url: b.url, lg: b.url }));
    } else {
        form.category_id = categories.value[0]?.id ?? null;
        if (route.query.chien_dich) form.campaign_id = Number(route.query.chien_dich);
        else if (isIncome.value) form.campaign_id = newestCampaignId();
        if (route.query.sepay) {
            const t = await adminApi(code.value, `/sepay/${Number(route.query.sepay)}`).catch(() => null);
            if (t) {
                sepay.value = t;
                await applyScan({ id: null, draft: t.draft, attached: t.status !== 'pending', message: '' });
                scan.value = null;
            }
        }
        if (route.query.claim) {
            const c = await adminApi(code.value, `/claims/${Number(route.query.claim)}`).catch(() => null);
            if (c) {
                claim.value = c;
                await applyScan({ id: null, draft: c.draft, attached: c.status !== 'pending', message: '' });
                scan.value = null;
                if (c.bill_url) bills.value.push({ claim: true, url: c.bill_url, lg: c.bill_url });
            }
        }
        if (route.query.scan) {
            const s = await adminApi(code.value, `/bill-scans/${Number(route.query.scan)}`).catch(() => null);
            if (s) await applyScan(s);
        }
    }
    ready.value = true;
});

onBeforeUnmount(() => [...photos.value, ...bills.value].forEach((p) => p.file && URL.revokeObjectURL(p.url)));

async function addPhotos(event) {
    const files = [...event.target.files].slice(0, MAX_PHOTOS - photos.value.length);
    event.target.value = '';
    if (event.target.files?.length > files.length) toast(`Mỗi phiếu tối đa ${MAX_PHOTOS} ảnh.`, 'error');
    for (const file of files) {
        const small = await shrinkImage(file);
        photos.value.push({ file: small, url: previewUrl(small), lg: previewUrl(small) });
    }
}
function removePhoto(i) {
    const [p] = photos.value.splice(i, 1);
    if (p.id) removedPhotoIds.value.push(p.id);
    else URL.revokeObjectURL(p.url);
}
async function addBill(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    const small = await shrinkImage(file);
    bills.value.push({ file: small, url: previewUrl(small), lg: previewUrl(small) });
}
function removeBill(i) {
    const [b] = bills.value.splice(i, 1);
    if (b.id) removedBillIds.value.push(b.id);
    else if (b.file) URL.revokeObjectURL(b.url);
}

function payload() {
    const data = new FormData();
    data.append('type', form.type);
    data.append('amount', form.amount ?? '');
    data.append('category_id', form.category_id ?? '');
    data.append('occurred_at', `${form.date} ${form.time || '00:00'}`);
    data.append('title', form.title.trim());
    data.append('description', form.description);
    data.append('contact_id', form.contact_id ?? '');
    data.append('payer_name', form.contact_id ? '' : form.payer_name.trim());
    data.append('campaign_id', isIncome.value && form.campaign_id ? form.campaign_id : '');
    data.append('show_on_timeline', form.show_on_timeline ? '1' : '0');
    photos.value.filter((p) => p.file).forEach((p) => data.append('photos[]', p.file));
    removedPhotoIds.value.forEach((x) => data.append('remove_photo_ids[]', x));
    // Giữ đúng thứ tự trên màn hình: ảnh cũ "p:id", ảnh mới "n:thứ tự".
    let n = 0;
    photos.value.forEach((p) => data.append('photo_order[]', p.id ? `p:${p.id}` : `n:${n++}`));
    if (sepay.value?.status === 'pending') data.append('sepay_transaction_id', sepay.value.id);
    if (claim.value?.status === 'pending') data.append('payment_claim_id', claim.value.id);
    const scanned = bills.value.find((b) => b.scanId);
    if (scanned && !scan.value?.attached) data.append('bill_scan_id', scanned.scanId);
    const newBill = bills.value.find((b) => b.file);
    if (newBill) data.append('bill', newBill.file);
    removedBillIds.value.forEach((x) => data.append('remove_bill_ids[]', x));
    return data;
}

async function save() {
    const saved = await submit(() =>
        adminApi(code.value, id.value ? `/transactions/${id.value}` : '/transactions', { method: 'POST', body: payload() }),
    ).catch(ignore);
    if (!saved) return;
    toast(id.value ? 'Đã lưu thay đổi.' : `Đã lưu ${isIncome.value ? 'phiếu thu' : 'phiếu chi'}.`);
    router.push(sepay.value ? `/${code.value}/quan-tri/cho-duyet` : claim.value ? `/${code.value}/quan-tri/cho-duyet?loai=bill` : `/${code.value}/quan-tri/phieu`);
}

async function remove() {
    if (!confirm('Xóa phiếu này? Phiếu đã xóa khôi phục được trong 30 ngày.')) return;
    await adminApi(code.value, `/transactions/${id.value}`, { method: 'DELETE' });
    toast('Đã xóa phiếu.');
    router.push(`/${code.value}/quan-tri/phieu`);
}

async function restore() {
    detail.value = await adminApi(code.value, `/transactions/${id.value}/restore`, { method: 'POST' });
    toast('Đã khôi phục phiếu.');
}

const errorOf = (key) => state.errors[key] ?? Object.entries(state.errors).find(([k]) => k.startsWith(`${key}.`))?.[1];
const actionLabel = { created: 'Tạo phiếu', updated: 'Sửa', deleted: 'Xóa', restored: 'Khôi phục' };
const crumbs = computed(() => [
    sepay.value || claim.value
        ? { label: 'Chờ duyệt', to: `/${code.value}/quan-tri/cho-duyet${claim.value ? '?loai=bill' : ''}` }
        : { label: 'Phiếu thu chi', to: `/${code.value}/quan-tri/phieu` },
]);
const title = computed(() => (id.value ? (isIncome.value ? 'Sửa phiếu thu' : 'Sửa phiếu chi') : isIncome.value ? 'Phiếu thu mới' : 'Phiếu chi mới'));
</script>

<template>
    <AdminShell :title="title" :crumbs="crumbs">
        <div v-if="!ready" class="py-10 text-center text-sm text-muted-foreground">Đang tải…</div>

        <form v-else class="mx-auto max-w-xl space-y-4 pb-24 lg:pb-0" @submit.prevent="save">
            <div v-if="deleted" class="flex items-center justify-between gap-3 rounded-xl border border-expense/40 bg-expense/5 p-3 text-sm">
                <span>Phiếu đã xóa, khôi phục được tới {{ formatDate(detail.restorable_until) }}.</span>
                <button type="button" class="btn-outline !min-h-10 shrink-0" @click="restore"><RotateCcw class="size-4" /> Khôi phục</button>
            </div>

            <!-- Duyệt giao dịch SePay qua form -->
            <div v-if="sepay" class="card p-3 text-sm">
                <p class="font-medium">Giao dịch SePay {{ sepay.transfer_type === 'in' ? 'tiền vào' : 'tiền ra' }} · {{ formatDateTime(sepay.transaction_date) }}</p>
                <p class="mt-1 break-words rounded bg-muted px-2 py-1 font-mono">{{ sepay.content || '(không có nội dung)' }}</p>
                <p class="mt-1 text-muted-foreground">{{ sepay.status === 'pending' ? 'Kiểm tra các ô có nhãn "Tự điền" rồi bấm Lưu để duyệt.' : 'Giao dịch này đã được xử lý.' }}</p>
            </div>

            <!-- Duyệt bill thành viên gửi qua form -->
            <div v-if="claim" class="card p-3 text-sm">
                <p class="font-medium">Bill {{ claim.name }} gửi · {{ formatDateTime(claim.submitted_at) }}</p>
                <p v-if="claim.note" class="mt-1">“{{ claim.note }}”</p>
                <p class="mt-1 text-muted-foreground">{{ claim.status === 'pending' ? `Người gửi khai ${formatMoney(claim.amount)}. Kiểm tra các ô rồi bấm Lưu để duyệt.` : 'Bill này đã được xử lý.' }}</p>
                <p v-if="state.errors.payment_claim_id" class="mt-1 text-expense">{{ state.errors.payment_claim_id }}</p>
            </div>

            <!-- Ảnh bill: AI đọc và điền sẵn (plan/04) -->
            <div v-if="scan" class="card flex items-center gap-3 p-3">
                <button v-if="scan.bill_url" type="button" class="h-20 w-14 shrink-0 overflow-hidden rounded-lg bg-muted" @click="viewing = { list: [{ lg: scan.bill_url }], start: 0 }"><img :src="scan.bill_url" alt="Ảnh bill" class="size-full object-cover" /></button>
                <div class="text-sm">
                    <p class="font-medium">Ảnh bill chuyển khoản</p>
                    <p class="text-muted-foreground">{{ scan.attached ? 'Bill này đã gắn vào một phiếu khác.' : scan.message }}</p>
                </div>
            </div>
            <label v-else-if="!id && !sepay && !claim" class="card flex cursor-pointer items-center gap-3 border-dashed p-3 hover:bg-muted">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary"><ScanText class="size-6" /></span>
                <span class="text-sm"><span class="block font-medium">Có ảnh bill chuyển khoản?</span><span class="text-muted-foreground">Chọn ảnh, AI đọc và điền sẵn phiếu giúp bạn.</span></span>
                <input type="file" accept="image/*" class="sr-only" @change="onBill" />
            </label>

            <!-- Thu / Chi -->
            <p v-if="isAi('type')" class="-mb-3 text-sm font-medium">Thu hay chi <span class="ml-1 rounded-md bg-primary/15 px-1.5 py-0.5 text-[10px] font-semibold text-primary">{{ aiTag }}</span></p>
            <div class="grid grid-cols-2 rounded-xl bg-muted p-1 text-center font-semibold" :class="{ 'ring-2 ring-primary/40': isAi('type') }" role="radiogroup" aria-label="Thu hay chi">
                <button type="button" role="radio" :aria-checked="isIncome" class="min-h-10 rounded-lg" :class="isIncome ? 'bg-card text-income shadow' : 'text-muted-foreground'" @click="form.type = 'income'">Thu</button>
                <button type="button" role="radio" :aria-checked="!isIncome" class="min-h-10 rounded-lg" :class="!isIncome ? 'bg-card text-expense shadow' : 'text-muted-foreground'" @click="form.type = 'expense'">Chi</button>
            </div>

            <FormField label="Số tiền" required :ai="isAi('amount') && aiTag" :error="state.errors.amount">
                <MoneyInput v-model="form.amount" class="!text-2xl !font-semibold" :class="{ 'field-error': state.errors.amount, 'ai-field': isAi('amount') }" placeholder="0" :autofocus="!scan" />
            </FormField>

            <div>
                <p class="mb-1.5 text-sm font-medium">Loại<span class="text-expense"> *</span><template v-if="isAi('category_id')"> <span class="ml-1 rounded-md bg-primary/15 px-1.5 py-0.5 text-[10px] font-semibold text-primary">{{ aiTag }}</span></template></p>
                <div class="flex flex-wrap gap-2">
                    <button v-for="c in categories" :key="c.id" type="button" class="inline-flex min-h-10 items-center gap-2 rounded-full border px-3 text-sm font-medium" :class="form.category_id === c.id ? 'border-transparent text-white' : 'border-border bg-card'" :style="form.category_id === c.id ? { backgroundColor: c.color || '#15803d' } : {}" @click="form.category_id = c.id">
                        <CategoryIcon :name="c.icon" :color="form.category_id === c.id ? '#ffffff' : c.color" size="size-6" class="!bg-transparent" /> {{ c.name }}
                    </button>
                    <RouterLink :to="`/${code}/quan-tri/loai-thu-chi`" class="inline-flex min-h-10 items-center rounded-full px-3 text-sm text-primary">+ Loại khác</RouterLink>
                </div>
                <p v-if="state.errors.category_id" class="mt-1 text-sm text-expense">{{ state.errors.category_id }}</p>
            </div>

            <FormField v-if="isIncome && options.campaigns.length" label="Chiến dịch" :ai="isAi('campaign_id') && aiTag" :error="state.errors.campaign_id">
                <select v-model="form.campaign_id" class="field" :class="{ 'ai-field': isAi('campaign_id') }">
                    <option :value="null">Không thuộc chiến dịch</option>
                    <option v-for="c in options.campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </FormField>

            <div class="grid grid-cols-2 gap-2">
                <FormField label="Ngày" required :ai="isAi('date') && aiTag" :error="state.errors.occurred_at"><input v-model="form.date" type="date" class="field" :class="{ 'ai-field': isAi('date') }" required /></FormField>
                <FormField label="Giờ"><input v-model="form.time" type="time" class="field" :class="{ 'ai-field': isAi('date') }" /></FormField>
            </div>

            <div class="relative">
                <FormField :label="isIncome ? 'Người đóng' : 'Người nhận / được thăm'" :ai="isAi('person') && aiTag" :error="state.errors.contact_id || state.errors.payer_name" :hint="form.contact_id ? 'Trong danh bạ' : personQuery ? 'Tên tự do, không có trong danh bạ' : 'Gõ để tìm trong danh bạ, không dấu cũng được'">
                    <input :value="personQuery" class="field" :class="{ 'ai-field': isAi('person') }" autocomplete="off" placeholder="Tên người" role="combobox" :aria-expanded="personOpen" @input="onPersonInput($event.target.value)" @focus="personOpen = true" @blur="closePerson" />
                </FormField>
                <ul v-if="personOpen && personMatches.length" class="absolute inset-x-0 top-[4.75rem] z-10 max-h-64 overflow-y-auto rounded-xl border border-border bg-card shadow-lg">
                    <li v-for="c in personMatches" :key="c.id">
                        <button type="button" class="block min-h-11 w-full px-3 text-left hover:bg-muted" @mousedown.prevent="pickContact(c)">{{ c.name }}</button>
                    </li>
                </ul>
                <div v-if="candidates.length && !form.contact_id" class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-muted-foreground">Có thể là:</span>
                    <button v-for="c in candidates" :key="c.id" type="button" class="chip !min-h-9 !px-3 !text-sm" @click="pickContact({ id: c.id, name: c.name })">{{ c.name }}</button>
                </div>
            </div>

            <FormField v-model="form.title" label="Tiêu đề" :error="state.errors.title" :placeholder="suggestedTitle || 'Vd: Thăm ốm bạn Lan'" maxlength="150" hint="Tự gợi ý từ loại và tên, sửa được." @input="titleTouched = true" />

            <FormField label="Mô tả" :error="state.errors.description">
                <textarea v-model="form.description" rows="3" class="field" maxlength="5000" placeholder="Ghi chú thêm, hiện trên timeline"></textarea>
            </FormField>

            <!-- Ảnh sự kiện -->
            <div>
                <p class="mb-1.5 text-sm font-medium">Ảnh sự kiện <span class="font-normal text-muted-foreground">(hiện trên timeline, tối đa {{ MAX_PHOTOS }})</span></p>
                <div class="grid grid-cols-4 gap-2">
                    <div v-for="(p, i) in photos" :key="p.id ?? p.url" class="relative aspect-square overflow-hidden rounded-xl bg-muted">
                        <button type="button" class="size-full" @click="viewing = { list: photos, start: i }"><img :src="p.url" alt="" class="size-full object-cover" /></button>
                        <button type="button" class="absolute right-1 top-1 grid size-7 place-items-center rounded-full bg-black/60 text-white" aria-label="Bỏ ảnh" @click="removePhoto(i)"><X class="size-4" /></button>
                    </div>
                    <label v-if="photos.length < MAX_PHOTOS" class="grid aspect-square cursor-pointer place-items-center rounded-xl border-2 border-dashed border-border text-muted-foreground hover:bg-muted">
                        <span class="flex flex-col items-center gap-1 text-xs"><ImagePlus class="size-6" />Thêm ảnh</span>
                        <input type="file" accept="image/*" multiple class="sr-only" @change="addPhotos" />
                    </label>
                </div>
                <p v-if="errorOf('photos')" class="mt-1 text-sm text-expense">{{ errorOf('photos') }}</p>
            </div>

            <!-- Ảnh bill chứng từ -->
            <div class="card p-3">
                <p class="flex items-center gap-2 text-sm font-medium"><ReceiptText class="size-4" /> Ảnh bill chứng từ</p>
                <p class="text-xs text-muted-foreground">Chỉ quản trị viên xem được, không hiện trên timeline.</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <div v-for="(b, i) in bills" :key="b.id ?? b.url" class="relative h-24 w-16 overflow-hidden rounded-lg bg-muted">
                        <button type="button" class="size-full" @click="viewing = { list: bills, start: i }"><img :src="b.url" alt="Ảnh bill" class="size-full object-cover" /></button>
                        <button v-if="!b.claim" type="button" class="absolute right-0.5 top-0.5 grid size-6 place-items-center rounded-full bg-black/60 text-white" aria-label="Bỏ ảnh bill" @click="removeBill(i)"><X class="size-3.5" /></button>
                    </div>
                    <label v-if="!bills.some((b) => b.file)" class="grid h-24 w-16 cursor-pointer place-items-center rounded-lg border-2 border-dashed border-border text-muted-foreground hover:bg-muted">
                        <Camera class="size-5" />
                        <input type="file" accept="image/*" class="sr-only" @change="addBill" />
                    </label>
                </div>
                <p v-if="state.errors.bill || state.errors.bill_scan_id" class="mt-1 text-sm text-expense">{{ state.errors.bill || state.errors.bill_scan_id }}</p>
            </div>

            <label class="flex min-h-11 items-center gap-3">
                <input v-model="form.show_on_timeline" type="checkbox" class="size-5 accent-[var(--primary)]" />
                <span><span class="block font-medium">Hiện trên timeline</span><span class="text-sm text-muted-foreground">Tắt nếu là khoản nội bộ; vẫn tính vào số dư.</span></span>
            </label>

            <!-- Lịch sử sửa -->
            <section v-if="detail?.histories?.length" class="card p-4">
                <h2 class="flex items-center gap-2 font-semibold"><History class="size-4" /> Lịch sử</h2>
                <ol class="mt-2 space-y-2 text-sm">
                    <li v-for="h in detail.histories" :key="h.id">
                        <p><span class="font-medium">{{ actionLabel[h.action] }}</span> · {{ h.user ?? 'Hệ thống' }} · <span class="text-muted-foreground">{{ formatDateTime(h.at) }}</span></p>
                        <ul v-if="h.changes.length" class="mt-0.5 space-y-0.5 pl-3 text-muted-foreground">
                            <li v-for="(c, i) in h.changes" :key="i">{{ c.field }}: <template v-if="c.from !== null">{{ c.from }} → </template>{{ c.to }}</li>
                        </ul>
                    </li>
                </ol>
            </section>

            <button v-if="id && !deleted" type="button" class="btn w-full text-expense hover:bg-expense/10" @click="remove"><Trash2 class="size-4" /> Xóa phiếu</button>

            <div class="fixed inset-x-0 bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-20 border-t border-border bg-card p-3 lg:static lg:border-0 lg:bg-transparent lg:p-0">
                <button type="submit" class="w-full" :class="isIncome ? 'btn-primary' : 'btn-danger'" :disabled="state.busy || deleted">{{ state.busy ? 'Đang lưu…' : id ? 'Lưu thay đổi' : isIncome ? 'Lưu phiếu thu' : 'Lưu phiếu chi' }}</button>
            </div>
        </form>

        <PhotoViewer v-if="viewing" :photos="viewing.list.map((p) => ({ lg: p.lg }))" :start="viewing.start" @close="viewing = null" />
    </AdminShell>
</template>
