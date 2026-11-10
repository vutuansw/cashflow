<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { CheckCircle2, ChevronDown, CircleDashed, CircleDot, Clock, Copy, Landmark, Lock, ReceiptText, Share2 } from 'lucide-vue-next';
import BackButton from '../components/BackButton.vue';
import BankAppSheet from '../components/BankAppSheet.vue';
import ClaimSheet from '../components/ClaimSheet.vue';
import MemberName from '../components/MemberName.vue';
import { api, ApiError } from '../lib/api.js';
import { copyText } from '../lib/clipboard.js';
import { formatMoney } from '../lib/money.js';
import { formatDate } from '../lib/time.js';
import { BANKS } from '../lib/banks.js';
import SiteFooter from '../components/SiteFooter.vue';

// Trang đóng quỹ /{ma-nhom}/campaigns/{slug}: tiến độ, QR, ai đã đóng/chưa đóng (chỉ tên). Xem plan/05 mục 1 và wireframe M2 màn 4.
// Điện thoại: mở thẳng app ngân hàng (mục 5b). Đã chuyển rồi thì gửi bill cho thủ quỹ (mục 5).
const route = useRoute();
const code = computed(() => route.params.group);
const data = ref(null);
const group = ref(null);
const error = ref(null);
const tab = ref('paid');
const claiming = ref(false);
const choosingApp = ref(false);
const apps = ref([]);
// Trên điện thoại không tự quét được mã QR đang hiện trên chính màn hình.
const isPhone = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

async function load() {
    data.value = await api(`/g/${code.value}/campaigns/${route.params.slug}`);
}

onMounted(async () => {
    group.value = await api(`/g/${code.value}`).catch(() => null);
    try {
        await load();
        document.title = `${data.value.name} · ${group.value?.name ?? 'WePo'}`;
    } catch (e) {
        error.value = e instanceof ApiError ? e.status : 500;
        return;
    }
    // VietQR lỗi thì không có lưới app, trang vẫn dùng QR và các nút Chép.
    if (isPhone && data.value.bank) {
        apps.value = (await api(`/g/${code.value}/campaigns/${route.params.slug}/bank-apps`).catch(() => null))?.apps ?? [];
    }
});

const useApps = computed(() => isPhone && apps.value.length > 0);
const paid = computed(() => (data.value?.people ?? []).filter((p) => p.status === 'paid' || p.status === 'partial'));
const unpaid = computed(() => (data.value?.people ?? []).filter((p) => p.status === 'unpaid'));
// Danh sách dài thì chỉ hiện 5 người đầu, bấm "Xem thêm" để mở hết; đổi tab thì thu lại.
const PREVIEW = 5;
const expanded = ref(false);
const list = computed(() => (tab.value === 'paid' ? paid.value : unpaid.value));
const shown = computed(() => (expanded.value ? list.value : list.value.slice(0, PREVIEW)));
watch(tab, () => (expanded.value = false));
const percent = computed(() => {
    const d = data.value;
    if (!d) return 0;
    if (d.members_total) return Math.min(100, (d.members_paid / d.members_total) * 100);
    const target = d.target_amount || d.expected_total;
    return target ? Math.min(100, (d.collected / target) * 100) : 0;
});
const bankName = computed(() => BANKS.find((b) => b.code === data.value?.bank?.code)?.name ?? data.value?.bank?.code);
const accountDisplay = computed(() => (data.value?.bank?.account_no ?? '').replace(/(\d{4})(?=\d)/g, '$1 '));

async function share() {
    const url = location.href;
    const text = `${data.value.name}: chuyển khoản theo hướng dẫn trên trang này nhé. Chuyển rồi thì gửi bill ngay trên trang.`;
    if (navigator.share) {
        try {
            await navigator.share({ title: data.value.name, text, url });
            return;
        } catch {
            // Người dùng đóng bảng chia sẻ: không làm gì.
            return;
        }
    }
    copyText(url, 'Đã chép link trang đóng quỹ.');
}
</script>

<template>
    <div class="mx-auto min-h-dvh max-w-xl pb-10">
        <header class="sticky top-0 z-10 flex min-h-14 items-center gap-2 border-b border-border bg-background/90 px-2 backdrop-blur">
            <BackButton :to="`/${code}`" label="trang nhóm" />
            <p class="min-w-0 flex-1 truncate font-semibold">{{ group?.name ?? code }}</p>
            <button v-if="data" type="button" class="icon-btn" aria-label="Chia sẻ" @click="share"><Share2 class="size-5" /></button>
        </header>

        <div v-if="error === 403" class="card m-4 p-6 text-center">
            <Lock class="mx-auto size-8 text-muted-foreground" />
            <p class="mt-3 font-semibold">Nhóm riêng tư</p>
            <RouterLink :to="`/login?next=${route.fullPath}`" class="btn-primary mt-4 w-full">Đăng nhập</RouterLink>
        </div>
        <div v-else-if="error" class="card m-4 p-6 text-center">
            <p class="font-semibold">Không tìm thấy chiến dịch</p>
            <RouterLink :to="`/${code}`" class="btn-outline mt-4">Về trang nhóm</RouterLink>
        </div>

        <div v-else-if="data" class="space-y-3 p-4">
            <section>
                <h1 class="text-xl font-semibold">{{ data.name }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ data.amount_per_member ? `${formatMoney(data.amount_per_member)} / người` : 'Đóng tùy tâm' }}<template v-if="data.ends_on"> · hạn {{ formatDate(data.ends_on) }}</template>
                    <template v-if="data.status === 'closed'"> · <span class="font-medium text-expense">Đã kết thúc</span></template>
                </p>
                <p v-if="data.description" class="mt-2 whitespace-pre-line text-sm">{{ data.description }}</p>
                <div class="mt-3 h-2.5 rounded-full bg-muted" role="progressbar" :aria-valuenow="Math.round(percent)" aria-valuemax="100"><div class="h-2.5 rounded-full bg-primary" :style="{ width: `${percent}%` }"></div></div>
                <p class="mt-1 text-sm">
                    <template v-if="data.members_total"><b>{{ data.members_paid }}/{{ data.members_total }}</b> người · </template>
                    {{ formatMoney(data.collected) }}<template v-if="data.target_amount || data.expected_total"> / {{ formatMoney(data.target_amount || data.expected_total) }}</template>
                </p>
            </section>

            <!-- Chuyển khoản: điện thoại mở app ngân hàng, máy tính quét QR -->
            <section v-if="data.bank" class="card p-4 text-center">
                <template v-if="useApps">
                    <button type="button" class="btn-primary w-full" @click="choosingApp = true"><Landmark class="size-5" /> Mở app ngân hàng để chuyển</button>
                    <p class="mt-3 font-semibold">{{ bankName }} · {{ accountDisplay }}</p>
                    <p class="text-sm text-muted-foreground">{{ data.bank.account_name }}</p>
                </template>
                <template v-else>
                    <img :src="data.bank.qr_url" alt="Mã QR chuyển khoản" class="mx-auto aspect-square w-52 rounded-xl bg-white object-contain" />
                    <p class="mt-3 font-semibold">{{ bankName }} · {{ accountDisplay }}</p>
                    <p class="text-sm text-muted-foreground">{{ data.bank.account_name }}</p>
                </template>
                <div class="mt-3 space-y-2 text-left text-sm">
                    <div v-if="data.amount_per_member" class="flex items-center justify-between gap-2 rounded-xl bg-muted px-3 py-2">
                        <span>Số tiền: <b>{{ formatMoney(data.amount_per_member) }}</b></span>
                        <button type="button" class="inline-flex min-h-9 items-center gap-1 font-semibold text-primary" @click="copyText(String(data.amount_per_member), 'Đã chép số tiền.')"><Copy class="size-4" /> Chép</button>
                    </div>
                    <!-- Nội dung CK chỉ có khi biết người xem (plan/05 mục 1); khách thì để app ngân hàng tự điền tên người chuyển. -->
                    <div v-if="data.transfer_content" class="flex items-center justify-between gap-2 rounded-xl bg-muted px-3 py-2">
                        <span>Nội dung: <b class="font-mono">{{ data.transfer_content }}</b></span>
                        <button type="button" class="inline-flex min-h-9 items-center gap-1 font-semibold text-primary" @click="copyText(data.transfer_content, 'Đã chép nội dung chuyển khoản.')"><Copy class="size-4" /> Chép</button>
                    </div>
                    <div class="flex items-center justify-between gap-2 rounded-xl bg-muted px-3 py-2">
                        <span>Số tài khoản: <b>{{ data.bank.account_no }}</b></span>
                        <button type="button" class="inline-flex min-h-9 items-center gap-1 font-semibold text-primary" @click="copyText(data.bank.account_no, 'Đã chép số tài khoản.')"><Copy class="size-4" /> Chép</button>
                    </div>
                </div>
                <div v-if="useApps" class="mt-3 flex items-center gap-3 rounded-xl border border-border p-2 text-left">
                    <img :src="data.bank.qr_url" alt="Mã QR chuyển khoản" class="aspect-square w-24 shrink-0 rounded-lg bg-white object-contain" />
                    <p class="text-xs text-muted-foreground">Mã QR cho người khác quét hộ, hoặc khi bạn mở trang này trên máy tính.</p>
                </div>
            </section>
            <p v-else-if="data.status === 'open'" class="rounded-xl bg-muted p-3 text-sm text-muted-foreground">Nhóm chưa khai báo tài khoản nhận quỹ, hỏi thủ quỹ để chuyển khoản nhé.</p>

            <!-- Đã chuyển rồi: gửi bill cho thủ quỹ -->
            <button v-if="data.status === 'open'" type="button" class="card flex w-full items-center gap-3 p-3 text-left hover:bg-muted" @click="claiming = true">
                <ReceiptText class="size-6 shrink-0 text-primary" />
                <span class="min-w-0 flex-1 text-sm"><span class="block font-semibold">Tôi đã chuyển khoản, gửi bill</span><span class="text-muted-foreground">Gửi ảnh bill để thủ quỹ ghi nhận cho bạn</span></span>
            </button>

            <!-- Đã đóng / chưa đóng -->
            <div class="grid grid-cols-2 rounded-xl bg-muted p-1 text-center text-sm font-semibold" role="tablist">
                <button type="button" role="tab" :aria-selected="tab === 'paid'" class="min-h-10 rounded-lg" :class="tab === 'paid' ? 'bg-card shadow' : 'text-muted-foreground'" @click="tab = 'paid'">Đã đóng ({{ paid.length }})</button>
                <button type="button" role="tab" :aria-selected="tab === 'unpaid'" class="min-h-10 rounded-lg" :class="tab === 'unpaid' ? 'bg-card shadow' : 'text-muted-foreground'" @click="tab = 'unpaid'">Chưa đóng ({{ unpaid.length }})</button>
            </div>
            <ul class="card divide-y divide-border text-sm">
                <li v-for="(p, i) in shown" :key="i" class="flex items-center justify-between gap-3 px-3 py-2.5">
                    <span class="flex min-w-0 items-center gap-2">
                        <CheckCircle2 v-if="p.status === 'paid'" class="size-4 shrink-0 text-income" aria-label="Đã đủ" />
                        <CircleDot v-else-if="p.status === 'partial'" class="size-4 shrink-0 text-amber-600" aria-label="Đóng thiếu" />
                        <CircleDashed v-else class="size-4 shrink-0 text-muted-foreground" aria-label="Chưa đóng" />
                        <MemberName :name="p.name" :member-id="p.member_id" :code="code" class="truncate" />
                    </span>
                    <span v-if="p.pending_claim" class="chip shrink-0 !bg-amber-100 !text-amber-800"><Clock class="size-3" /> Chờ xác nhận</span>
                    <span v-else-if="p.status === 'partial'" class="shrink-0 tabular-nums text-amber-700">{{ formatMoney(p.paid).replace(' đ', '') }}/{{ formatMoney(p.expected) }}</span>
                    <span v-else-if="p.last_paid_at" class="shrink-0 text-muted-foreground">{{ formatDate(p.last_paid_at).slice(0, 5) }}</span>
                </li>
                <li v-if="!list.length" class="p-4 text-center text-muted-foreground">{{ tab === 'paid' ? 'Chưa có ai đóng.' : 'Mọi người đã đóng đủ. Cảm ơn cả nhóm!' }}</li>
                <li v-if="list.length > PREVIEW">
                    <button type="button" class="flex min-h-11 w-full items-center justify-center gap-1 font-semibold text-primary" :aria-expanded="expanded" @click="expanded = !expanded">
                        {{ expanded ? 'Thu gọn' : `Xem thêm ${list.length - PREVIEW} người` }}
                        <ChevronDown class="size-4 transition-transform" :class="{ 'rotate-180': expanded }" />
                    </button>
                </li>
            </ul>
            <p class="text-center text-xs text-muted-foreground">Khoản chuyển khoản được cập nhật sau khi thủ quỹ xác nhận.</p>

            <button type="button" class="btn-outline w-full" @click="share"><Share2 class="size-4" /> Chia sẻ trang này</button>

            <ClaimSheet :open="claiming" :code="code" :campaign="data" @close="claiming = false" @sent="load" />
            <BankAppSheet :open="choosingApp" :apps="apps" :content="data.transfer_content" @close="choosingApp = false" />
        </div>
        <SiteFooter class="hidden lg:block" />
    </div>
</template>
