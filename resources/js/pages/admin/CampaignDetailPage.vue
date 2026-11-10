<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { Banknote, CheckCircle2, CircleDashed, CircleDot, Copy, ExternalLink, Pencil, Phone } from 'lucide-vue-next';
import AdminShell from '../../layouts/AdminShell.vue';
import FormField from '../../components/FormField.vue';
import MoneyInput from '../../components/MoneyInput.vue';
import SegmentedControl from '../../components/SegmentedControl.vue';
import Sheet from '../../components/Sheet.vue';
import { adminApi } from '../../lib/api.js';
import { copyText } from '../../lib/clipboard.js';
import { useForm, ignore } from '../../lib/form.js';
import { formatMoney } from '../../lib/money.js';
import { formatDate } from '../../lib/time.js';
import { toast } from '../../lib/notice.js';

// Chi tiết chiến dịch cho QTV: ai đóng/chưa, SĐT người chưa đóng để gọi, chép danh sách nhắc, ghi nhận tiền mặt. Xem plan/05 mục 1.
const route = useRoute();
const code = computed(() => route.params.group);
const id = computed(() => Number(route.params.id));
const data = ref(null);
const filter = ref('unpaid');
const cash = reactive({ person: null, amount: null });
const { state, submit } = useForm();

const load = async () => (data.value = await adminApi(code.value, `/campaigns/${id.value}`));
onMounted(load);

const groups = computed(() => {
    const people = data.value?.people ?? [];
    return {
        unpaid: people.filter((p) => p.status === 'unpaid'),
        partial: people.filter((p) => p.status === 'partial'),
        paid: people.filter((p) => p.status === 'paid'),
        exempt: people.filter((p) => p.status === 'exempt'),
    };
});
const tabs = computed(() => [
    { v: 'unpaid', l: 'Chưa đóng' },
    { v: 'partial', l: 'Đóng thiếu' },
    { v: 'paid', l: 'Đã đủ' },
    { v: 'exempt', l: 'Miễn' },
].map((t) => ({ ...t, count: groups.value[t.v].length })));
const publicUrl = computed(() => `${location.origin}/${code.value}/campaigns/${data.value?.slug}`);

function copyReminder() {
    const lines = [...groups.value.unpaid, ...groups.value.partial].map((p, i) => `${i + 1}. ${p.name}${p.status === 'partial' ? ` (còn thiếu ${formatMoney(p.expected - p.paid)})` : ''}`);
    copyText(`${data.value.name}: còn ${lines.length} bạn chưa đóng đủ\n${lines.join('\n')}\n\nĐóng quỹ bằng QR tại: ${publicUrl.value}`, 'Đã chép danh sách nhắc, dán vào nhóm Zalo nhé.');
}

function openCash(p) {
    cash.person = p;
    cash.amount = p.expected ? Math.max(p.expected - p.paid, 0) || p.expected : data.value.amount_per_member;
}
async function saveCash() {
    const res = await submit(() => adminApi(code.value, `/campaigns/${id.value}/cash`, { method: 'POST', body: { contact_id: cash.person.contact_id, amount: cash.amount } })).catch(ignore);
    if (!res) return;
    data.value = res;
    toast(`Đã ghi nhận ${cash.person.name} đóng tiền mặt.`);
    cash.person = null;
}
</script>

<template>
    <AdminShell :title="data?.name ?? 'Chiến dịch'" :crumbs="[{ label: 'Chiến dịch', to: `/${code}/admin/campaigns` }]">
        <template #actions>
            <RouterLink v-if="data" :to="`/${code}/admin/campaigns/${id}/edit`" class="icon-btn" aria-label="Sửa chiến dịch"><Pencil class="size-5" /></RouterLink>
        </template>
        <p v-if="!data" class="py-10 text-center text-sm text-muted-foreground">Đang tải…</p>
        <div v-else class="space-y-3">
            <section class="card p-4">
                <p class="text-sm text-muted-foreground">
                    Mã CK <b class="font-mono text-foreground">{{ data.transfer_code }}</b> · {{ data.amount_per_member ? `${formatMoney(data.amount_per_member)}/người` : 'tùy tâm' }}<template v-if="data.ends_on"> · hạn {{ formatDate(data.ends_on) }}</template>
                    · <span :class="data.status === 'open' ? 'text-primary' : 'text-expense'">{{ data.status === 'open' ? 'Đang mở' : 'Đã đóng' }}</span>
                </p>
                <p class="mt-2 text-2xl font-semibold tabular-nums">{{ formatMoney(data.collected) }}<span v-if="data.expected_total" class="text-base font-normal text-muted-foreground"> / {{ formatMoney(data.expected_total) }}</span></p>
                <p class="text-sm text-muted-foreground">{{ data.members_paid }}/{{ data.members_total }} người đã đủ<template v-if="data.members_partial"> · {{ data.members_partial }} đóng thiếu</template></p>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <a :href="publicUrl" target="_blank" class="btn-outline !min-h-10 text-sm"><ExternalLink class="size-4" /> Trang đóng quỹ</a>
                    <button type="button" class="btn-outline !min-h-10 text-sm" @click="copyText(publicUrl, 'Đã chép link trang đóng quỹ.')"><Copy class="size-4" /> Chép link</button>
                </div>
                <button v-if="groups.unpaid.length + groups.partial.length" type="button" class="btn-primary mt-2 w-full !min-h-10 text-sm" @click="copyReminder"><Copy class="size-4" /> Chép danh sách chưa đóng để nhắc</button>
            </section>

            <SegmentedControl v-model="filter" :options="tabs" label="Lọc theo tình trạng đóng" />

            <ul class="card divide-y divide-border">
                <li v-for="(p, i) in groups[filter]" :key="p.contact_id ?? `n${i}`" class="flex items-center gap-3 px-3 py-2">
                    <CheckCircle2 v-if="p.status === 'paid'" class="size-5 shrink-0 text-income" />
                    <CircleDot v-else-if="p.status === 'partial'" class="size-5 shrink-0 text-amber-600" />
                    <CircleDashed v-else class="size-5 shrink-0 text-muted-foreground" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ p.name }}<span v-if="!p.in_list" class="ml-1 text-xs font-normal text-muted-foreground">(ngoài danh sách)</span></p>
                        <p class="text-xs text-muted-foreground">
                            <template v-if="p.paid">Đã đóng {{ formatMoney(p.paid) }}<template v-if="p.last_paid_at"> · {{ formatDate(p.last_paid_at) }}</template></template>
                            <template v-else-if="p.expected">Phải đóng {{ formatMoney(p.expected) }}</template>
                            <template v-if="p.status === 'partial'"> · còn thiếu {{ formatMoney(p.expected - p.paid) }}</template>
                        </p>
                    </div>
                    <a v-if="p.phone && ['unpaid', 'partial'].includes(p.status)" :href="`tel:${p.phone}`" class="icon-btn" :aria-label="`Gọi ${p.name}`"><Phone class="size-4" /></a>
                    <button v-if="p.contact_id && ['unpaid', 'partial'].includes(p.status) && data.status === 'open'" type="button" class="btn-outline !min-h-9 shrink-0 !px-2 text-xs" @click="openCash(p)"><Banknote class="size-4" /> Tiền mặt</button>
                </li>
                <li v-if="!groups[filter].length" class="p-4 text-center text-sm text-muted-foreground">Không có ai.</li>
            </ul>
        </div>

        <Sheet :open="Boolean(cash.person)" :title="`${cash.person?.name} đóng tiền mặt`" @close="cash.person = null">
            <form class="space-y-4" @submit.prevent="saveCash">
                <FormField label="Số tiền" required :error="state.errors.amount"><MoneyInput v-model="cash.amount" /></FormField>
                <p class="text-sm text-muted-foreground">Tạo một phiếu thu gắn chiến dịch này, ngày giờ là bây giờ. Sửa thêm được trong danh sách phiếu.</p>
                <button type="submit" class="btn-primary w-full" :disabled="state.busy">Ghi nhận đã đóng</button>
            </form>
        </Sheet>
    </AdminShell>
</template>
