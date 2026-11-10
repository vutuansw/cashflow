<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { AlertTriangle, Check, RotateCcw, Settings, UserRound } from 'lucide-vue-next';
import AdminShell from '../../layouts/AdminShell.vue';
import Sheet from '../../components/Sheet.vue';
import { adminApi } from '../../lib/api.js';
import { formatMoney } from '../../lib/money.js';
import { formatDateTime } from '../../lib/time.js';
import { toast } from '../../lib/notice.js';
import { pending } from '../../lib/pending.js';

// Hàng chờ duyệt giao dịch SePay, xem plan/05 mục 4 và wireframe M2 màn 3.
const route = useRoute();
const router = useRouter();
const code = computed(() => route.params.group);
const status = ref('pending');
const items = ref(null);
const counts = ref({});
const busy = ref(null);
const ignoring = ref(null);
const reason = ref('');
const sepayOn = ref(true);

async function load() {
    const r = await adminApi(code.value, `/sepay?status=${status.value}`);
    items.value = r.data;
    counts.value = r.counts;
    pending.count = r.counts.pending ?? 0;
}
onMounted(async () => {
    load();
    sepayOn.value = (await adminApi(code.value, '/settings').catch(() => null))?.sepay?.enabled ?? true;
});
watch(status, load);

const ready = computed(() => (items.value ?? []).filter((t) => t.ready));

function errorText(e) {
    return Object.values(e.errors ?? {})[0]?.[0] ?? e.message;
}

async function approve(t) {
    busy.value = t.id;
    try {
        await adminApi(code.value, `/sepay/${t.id}/approve`, { method: 'POST' });
        toast(`Đã duyệt ${formatMoney(t.amount)}${t.contact ? ` của ${t.contact.name}` : ''}.`);
        await load();
    } catch (e) {
        toast(errorText(e), 'error');
    } finally {
        busy.value = null;
    }
}

async function approveAll() {
    busy.value = 'all';
    try {
        const r = await adminApi(code.value, '/sepay/approve', { method: 'POST', body: { ids: ready.value.map((t) => t.id) } });
        toast(`Đã duyệt ${r.approved} giao dịch.${r.failed.length ? ` ${r.failed.length} giao dịch cần xem lại.` : ''}`);
        await load();
    } finally {
        busy.value = null;
    }
}

// Mở form phiếu điền sẵn để chọn lại người, loại, thêm ảnh (tiền ra luôn đi đường này).
const openForm = (t) => router.push(`/${code.value}/quan-tri/phieu/moi?sepay=${t.id}`);

async function ignore() {
    await adminApi(code.value, `/sepay/${ignoring.value.id}/ignore`, { method: 'POST', body: { reason: reason.value || null } });
    toast('Đã bỏ qua giao dịch.');
    ignoring.value = null;
    reason.value = '';
    load();
}

async function restore(t) {
    await adminApi(code.value, `/sepay/${t.id}/restore`, { method: 'POST' });
    toast('Đã đưa về hàng chờ.');
    load();
}
</script>

<template>
    <AdminShell title="Chờ duyệt">
        <div v-if="!sepayOn" class="card mb-3 flex items-center gap-3 p-3 text-sm">
            <Settings class="size-5 shrink-0 text-muted-foreground" />
            <span class="flex-1">Bật SePay để tiền chuyển khoản vào tài khoản nhóm tự hiện ở đây.</span>
            <RouterLink :to="`/${code}/quan-tri/cai-dat#sepay`" class="font-semibold text-primary">Cài đặt</RouterLink>
        </div>

        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 text-sm">
            <button v-for="s in [{ v: 'pending', l: 'Chờ duyệt' }, { v: 'ignored', l: 'Đã bỏ qua' }, { v: 'approved', l: 'Đã duyệt' }]" :key="s.v" type="button" class="chip !min-h-9 shrink-0 whitespace-nowrap !px-3.5 !text-sm" :class="status === s.v ? '!bg-primary !text-primary-foreground' : ''" @click="status = s.v">
                {{ s.l }}<template v-if="counts[s.v]"> ({{ counts[s.v] }})</template>
            </button>
        </div>

        <p v-if="!items" class="py-10 text-center text-sm text-muted-foreground">Đang tải…</p>
        <div v-else class="mt-3 space-y-3">
            <div v-if="status === 'pending' && ready.length > 1" class="card flex items-center justify-between gap-3 p-3">
                <p class="text-sm">{{ ready.length }} giao dịch đã nhận ra đủ chiến dịch và người đóng</p>
                <button type="button" class="btn-primary !min-h-9 shrink-0 !px-3 text-sm" :disabled="busy === 'all'" @click="approveAll">Duyệt cả {{ ready.length }}</button>
            </div>

            <article v-for="t in items" :key="t.id" class="card p-3" :class="{ 'border-amber-400/70': status === 'pending' && t.transfer_type === 'in' && !t.ready }">
                <div class="flex items-baseline justify-between gap-2">
                    <p class="font-semibold tabular-nums" :class="t.transfer_type === 'in' ? 'text-income' : 'text-expense'">{{ t.transfer_type === 'in' ? '+' : '−' }}{{ formatMoney(t.amount).replace(' đ', '') }}</p>
                    <p class="text-xs text-muted-foreground">{{ formatDateTime(t.transaction_date) }}</p>
                </div>
                <p class="mt-1 break-words rounded bg-muted px-2 py-1 font-mono text-sm">{{ t.content || '(không có nội dung)' }}</p>
                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                    <span v-if="t.campaign" class="chip !bg-primary/15 !text-primary">{{ t.campaign.name }}</span>
                    <span v-if="t.contact" class="chip"><UserRound class="size-3" /> {{ t.contact.name }}</span>
                    <span v-if="t.transfer_type === 'out'" class="chip">Tiền ra</span>
                    <span v-if="t.account_mismatch" class="chip !bg-amber-100 !text-amber-800"><AlertTriangle class="size-3" /> Khác tài khoản nhóm đã khai</span>
                </div>
                <p v-if="status === 'pending' && t.transfer_type === 'in' && !t.ready" class="mt-2 text-xs text-amber-700">{{ t.campaign ? 'Chưa nhận ra người đóng' : t.contact ? 'Chưa nhận ra chiến dịch' : 'Không nhận ra chiến dịch và người đóng' }}</p>
                <p v-if="t.ignore_reason" class="mt-2 text-xs text-muted-foreground">Lý do bỏ qua: {{ t.ignore_reason }}</p>

                <div v-if="status === 'pending'" class="mt-3 grid grid-cols-3 gap-2">
                    <button type="button" class="btn-outline !min-h-10 text-sm" @click="ignoring = t">Bỏ qua</button>
                    <button v-if="t.transfer_type === 'out'" type="button" class="btn-primary col-span-2 !min-h-10 text-sm" @click="openForm(t)">Tạo phiếu chi</button>
                    <button v-else type="button" class="btn-outline !min-h-10 text-sm" @click="openForm(t)">Sửa</button>
                    <button v-if="t.ready" type="button" class="btn-primary !min-h-10 text-sm" :disabled="busy === t.id" @click="approve(t)"><Check class="size-4" /> Duyệt</button>
                    <button v-else-if="t.transfer_type === 'in'" type="button" class="btn-primary !min-h-10 text-sm" @click="openForm(t)">Gán và duyệt</button>
                </div>
                <div v-else-if="status === 'ignored'" class="mt-3">
                    <button type="button" class="btn-outline !min-h-10 w-full text-sm" @click="restore(t)"><RotateCcw class="size-4" /> Đưa về hàng chờ</button>
                </div>
                <RouterLink v-else-if="t.transaction_id" :to="`/${code}/quan-tri/phieu/${t.transaction_id}`" class="mt-2 inline-block text-sm font-medium text-primary">Xem phiếu</RouterLink>
            </article>

            <p v-if="!items.length" class="card p-6 text-center text-sm text-muted-foreground">{{ status === 'pending' ? 'Không có giao dịch nào chờ duyệt.' : 'Chưa có giao dịch nào.' }}</p>
        </div>

        <Sheet :open="Boolean(ignoring)" title="Bỏ qua giao dịch" @close="ignoring = null">
            <p class="text-sm text-muted-foreground">Giao dịch không liên quan quỹ (tiền lãi, chuyển nhầm…). Xem lại được ở tab "Đã bỏ qua".</p>
            <input v-model="reason" class="field mt-3" maxlength="255" placeholder="Lý do (không bắt buộc)" />
            <button type="button" class="btn-primary mt-3 w-full" @click="ignore">Bỏ qua</button>
        </Sheet>
    </AdminShell>
</template>
