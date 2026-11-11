<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { AlertTriangle, Check, Copy, RotateCcw, Settings, Sparkles, UserRound } from 'lucide-vue-next';
import AdminShell from '../../layouts/AdminShell.vue';
import SegmentedControl from '../../components/SegmentedControl.vue';
import Sheet from '../../components/Sheet.vue';
import PhotoViewer from '../../components/PhotoViewer.vue';
import { adminApi } from '../../lib/api.js';
import { formatMoney } from '../../lib/money.js';
import { formatDateTime } from '../../lib/time.js';
import { toast } from '../../lib/notice.js';
import { refreshPending } from '../../lib/pending.js';

// Hàng chờ duyệt: giao dịch SePay (plan/05 mục 4, wireframe M2 màn 3) và bill thành viên tự gửi (mục 5).
const route = useRoute();
const router = useRouter();
const code = computed(() => route.params.group);
// Hai nguồn: "sepay" (tài khoản nhóm báo về) và "bill" (thành viên gửi ảnh bill). Giữ trên URL để Back quay đúng tab.
const source = ref(route.query.source === 'bill' ? 'bill' : 'sepay');
const status = ref('pending');
const items = ref(null);
const counts = ref({});
const totals = ref({ sepay: 0, claims: 0 });
const viewing = ref(null);
const busy = ref(null);
const ignoring = ref(null);
const reason = ref('');
const sepayOn = ref(true);

async function load() {
    const path = source.value === 'bill' ? '/claims' : '/sepay';
    const [r, c] = await Promise.all([adminApi(code.value, `${path}?status=${status.value}`), adminApi(code.value, '/sepay/count').catch(() => null)]);
    items.value = r.data;
    counts.value = r.counts;
    if (c) totals.value = c;
    refreshPending(code.value);
}
onMounted(async () => {
    const c = await adminApi(code.value, '/sepay/count').catch(() => null);
    // Không có giao dịch SePay chờ mà có bill thành viên thì mở thẳng tab bill.
    if (c && !route.query.source && !c.sepay && c.claims) source.value = 'bill';
    load();
    sepayOn.value = (await adminApi(code.value, '/settings').catch(() => null))?.sepay?.enabled ?? true;
});
watch(status, load);
watch(source, (v) => {
    items.value = null;
    status.value === 'pending' ? load() : (status.value = 'pending');
    router.replace({ query: { ...route.query, source: v === 'bill' ? 'bill' : undefined } });
});

// Chiến dịch đoán (nội dung không có mã) không đưa vào Duyệt cả loạt, QTV duyệt từng cái.
const ready = computed(() => (items.value ?? []).filter((t) => t.ready && !t.campaign_guessed));

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
const openForm = (t) => router.push(`/${code.value}/admin/transactions/new?sepay=${t.id}`);

async function ignore() {
    const path = source.value === 'bill' ? '/claims' : '/sepay';
    await adminApi(code.value, `${path}/${ignoring.value.id}/ignore`, { method: 'POST', body: { reason: reason.value || null } });
    toast(source.value === 'bill' ? 'Đã bỏ qua bill.' : 'Đã bỏ qua giao dịch.');
    ignoring.value = null;
    reason.value = '';
    load();
}

async function restore(t) {
    try {
        await adminApi(code.value, `${source.value === 'bill' ? '/claims' : '/sepay'}/${t.id}/restore`, { method: 'POST' });
        toast('Đã đưa về hàng chờ.');
        load();
    } catch (e) {
        toast(errorText(e), 'error');
    }
}

// Bill thành viên: duyệt thành phiếu mới, hoặc ghép với lần chuyển đã có (chống trùng, plan/05 mục 5).
async function approveClaim(c, mode) {
    if (mode === 'new' && c.duplicate && !confirm(`Bill này có thể trùng với ${c.duplicate.label}. Vẫn tạo phiếu thu riêng?`)) return;
    busy.value = c.id;
    try {
        await adminApi(code.value, `/claims/${c.id}/approve`, { method: 'POST', body: { mode, confirm: mode === 'new' && Boolean(c.duplicate) } });
        toast(mode === 'merge' ? `Đã gắn bill của ${c.name} vào phiếu.` : `Đã duyệt ${formatMoney(c.approve_amount)} của ${c.name}.`);
        await load();
    } catch (e) {
        toast(errorText(e), 'error');
    } finally {
        busy.value = null;
    }
}
// Số tiền trên dòng bill: số người gửi khai (quên chụp bill), không thì số AI đọc, rồi mức phải đóng (plan/23).
const shownAmount = (c) => c.amount ?? c.ai.amount ?? c.approve_amount;
const openClaimForm = (c) => router.push(`/${code.value}/admin/transactions/new?claim=${c.id}`);
const aiText = (c) =>
    ({ processing: 'AI đang đọc bill…', failed: 'AI không đọc được bill', none: 'Không có ảnh bill, đối chiếu với sao kê' })[c.ai.state] ??
    (c.ai.amount ? null : 'Ảnh không giống bill chuyển khoản');
</script>

<template>
    <AdminShell title="Chờ duyệt">
        <div v-if="!sepayOn && source === 'sepay'" class="card mb-3 flex items-center gap-3 p-3 text-sm">
            <Settings class="size-5 shrink-0 text-muted-foreground" />
            <span class="flex-1">Bật SePay để tiền chuyển khoản vào tài khoản nhóm tự hiện ở đây.</span>
            <RouterLink :to="`/${code}/admin/settings#sepay`" class="font-semibold text-primary">Cài đặt</RouterLink>
        </div>

        <!-- Nguồn (chuyển khoản / bill thành viên) và trạng thái: ô chọn liền khối như các màn lọc khác -->
        <div class="space-y-2">
            <SegmentedControl v-model="source" label="Nguồn" :options="[{ v: 'sepay', l: 'Chuyển khoản', count: totals.sepay, badge: true }, { v: 'bill', l: 'Bill thành viên', count: totals.claims, badge: true }]" />
            <SegmentedControl v-model="status" label="Trạng thái" :options="[{ v: 'pending', l: 'Chờ duyệt', count: counts.pending ?? 0 }, { v: 'ignored', l: 'Bỏ qua', count: counts.ignored ?? 0 }, { v: 'approved', l: 'Đã duyệt', count: counts.approved ?? 0 }]" />
        </div>

        <p v-if="!items" class="py-10 text-center text-sm text-muted-foreground">Đang tải…</p>
        <!-- Bill thành viên gửi -->
        <div v-else-if="source === 'bill'" class="mt-3 space-y-3">
            <article v-for="c in items" :key="c.id" class="card p-3" :class="{ 'border-amber-400/70': status === 'pending' && (c.duplicate || c.warnings.length) }">
                <div class="flex gap-3">
                    <button v-if="c.bill_url" type="button" class="h-24 w-16 shrink-0 overflow-hidden rounded-lg bg-muted" aria-label="Xem ảnh bill" @click="viewing = c.bill_url"><img :src="c.bill_url" alt="" class="size-full object-cover" /></button>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between gap-2">
                            <p class="truncate font-semibold">{{ c.name }}</p>
                            <p v-if="shownAmount(c)" class="shrink-0 font-semibold tabular-nums text-income">+{{ formatMoney(shownAmount(c)).replace(' đ', '') }}</p>
                            <p v-else class="shrink-0 text-sm text-muted-foreground">Chưa có số tiền</p>
                        </div>
                        <p class="text-xs text-muted-foreground">Gửi lúc {{ formatDateTime(c.submitted_at) }}<template v-if="c.amount === null && c.amount_source === 'expected'"> · số tiền theo mức phải đóng</template><template v-else-if="c.amount !== null"> · người gửi tự nhập số tiền</template></p>
                        <div class="mt-1.5 flex flex-wrap gap-1.5 text-xs">
                            <span v-if="c.campaign" class="chip !bg-primary/15 !text-primary">{{ c.campaign.name }}</span>
                            <span v-if="c.already_paid" class="chip">Đã đóng đủ</span>
                            <span v-if="!c.contact_id" class="chip">Ngoài danh bạ</span>
                        </div>
                    </div>
                </div>

                <div class="mt-2 rounded-lg bg-muted px-2 py-1.5 text-xs">
                    <p v-if="aiText(c)" class="text-muted-foreground"><Sparkles class="inline size-3" /> {{ aiText(c) }}</p>
                    <template v-else>
                        <p><Sparkles class="inline size-3 text-primary" /> AI đọc: <b class="tabular-nums">{{ formatMoney(c.ai.amount) }}</b><template v-if="c.ai.occurred_at"> · {{ c.ai.occurred_at.slice(11) }} {{ c.ai.occurred_at.slice(8, 10) }}/{{ c.ai.occurred_at.slice(5, 7) }}</template><template v-if="c.ai.bank_name"> · {{ c.ai.bank_name }}</template></p>
                        <p v-if="c.ai.note" class="mt-0.5 break-words font-mono">{{ c.ai.note }}</p>
                        <p v-if="c.ai.reference_code" class="mt-0.5 text-muted-foreground">Mã GD: {{ c.ai.reference_code }}</p>
                    </template>
                </div>
                <p v-if="c.note" class="mt-2 text-sm">“{{ c.note }}”</p>
                <p v-for="w in c.warnings" :key="w" class="mt-2 flex items-center gap-1 text-xs text-amber-700"><AlertTriangle class="size-3.5 shrink-0" /> {{ w }}</p>

                <div v-if="status === 'pending' && c.duplicate" class="mt-2 rounded-lg border p-2 text-xs" :class="c.duplicate.level === 'strong' ? 'border-expense/40 bg-expense/5' : 'border-amber-400/60 bg-amber-50 dark:bg-amber-950/30'">
                    <p class="flex items-start gap-1"><Copy class="mt-0.5 size-3.5 shrink-0" /><span>{{ c.duplicate.level === 'strong' ? 'Trùng mã giao dịch với' : 'Có thể trùng với' }} {{ c.duplicate.label }}.</span></p>
                    <RouterLink v-if="c.duplicate.transaction_id" :to="`/${code}/admin/transactions/${c.duplicate.transaction_id}`" class="mt-1 inline-block font-semibold text-primary">Xem phiếu</RouterLink>
                </div>
                <p v-if="c.ignore_reason" class="mt-2 text-xs text-muted-foreground">Lý do bỏ qua: {{ c.ignore_reason }}</p>

                <div v-if="status === 'pending'" class="mt-3 grid grid-cols-3 gap-2">
                    <button type="button" class="btn-outline !min-h-10 text-sm" @click="ignoring = c">Bỏ qua</button>
                    <template v-if="c.duplicate?.level === 'strong'">
                        <button type="button" class="btn-primary col-span-2 !min-h-10 text-sm" :disabled="busy === c.id" @click="approveClaim(c, 'merge')">{{ c.duplicate.transaction_id ? 'Gắn bill vào phiếu đã có' : 'Duyệt cùng giao dịch SePay' }}</button>
                    </template>
                    <template v-else-if="c.duplicate">
                        <button type="button" class="btn-outline !min-h-10 text-sm" :disabled="busy === c.id" @click="approveClaim(c, 'new')">Phiếu riêng</button>
                        <button type="button" class="btn-primary !min-h-10 text-sm" :disabled="busy === c.id" @click="approveClaim(c, 'merge')"><Check class="size-4" /> Ghép</button>
                    </template>
                    <template v-else>
                        <button type="button" class="btn-outline !min-h-10 text-sm" @click="openClaimForm(c)">Sửa</button>
                        <!-- Không có số tiền nào (AI không đọc được, không khai, không có mức phải đóng): QTV tự nhập. -->
                        <button v-if="!c.approve_amount && c.ai.state !== 'processing'" type="button" class="btn-primary !min-h-10 text-sm" @click="openClaimForm(c)">Nhập số tiền</button>
                        <button v-else type="button" class="btn-primary !min-h-10 text-sm" :disabled="busy === c.id || c.ai.state === 'processing'" @click="approveClaim(c, 'new')"><Check class="size-4" /> Duyệt</button>
                    </template>
                </div>
                <div v-else-if="status === 'ignored'" class="mt-3">
                    <button type="button" class="btn-outline !min-h-10 w-full text-sm" @click="restore(c)"><RotateCcw class="size-4" /> Đưa về hàng chờ</button>
                </div>
                <RouterLink v-else-if="c.transaction_id" :to="`/${code}/admin/transactions/${c.transaction_id}`" class="mt-2 inline-block text-sm font-medium text-primary">Xem phiếu</RouterLink>
            </article>

            <p v-if="!items.length" class="card p-6 text-center text-sm text-muted-foreground">{{ status === 'pending' ? 'Không có bill nào chờ duyệt. Thành viên gửi bill ở trang đóng quỹ của chiến dịch.' : 'Chưa có bill nào.' }}</p>
        </div>

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
                    <span v-if="t.campaign && t.campaign_guessed" class="chip !bg-amber-100 !text-amber-800" title="Nội dung không có mã chiến dịch; nhóm chỉ có một chiến dịch đang mở nên đoán là chiến dịch này.">Đoán: {{ t.campaign.name }}</span>
                    <span v-else-if="t.campaign" class="chip !bg-primary/15 !text-primary">{{ t.campaign.name }}</span>
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
                <RouterLink v-else-if="t.transaction_id" :to="`/${code}/admin/transactions/${t.transaction_id}`" class="mt-2 inline-block text-sm font-medium text-primary">Xem phiếu</RouterLink>
            </article>

            <p v-if="!items.length" class="card p-6 text-center text-sm text-muted-foreground">{{ status === 'pending' ? 'Không có giao dịch nào chờ duyệt.' : 'Chưa có giao dịch nào.' }}</p>
        </div>

        <Sheet :open="Boolean(ignoring)" :title="source === 'bill' ? 'Bỏ qua bill' : 'Bỏ qua giao dịch'" @close="ignoring = null">
            <p class="text-sm text-muted-foreground">{{ source === 'bill' ? 'Ảnh sai, trùng, không phải khoản đóng quỹ…' : 'Giao dịch không liên quan quỹ (tiền lãi, chuyển nhầm…).' }} Xem lại được ở tab "Đã bỏ qua".</p>
            <input v-model="reason" class="field mt-3" maxlength="255" placeholder="Lý do (không bắt buộc)" />
            <button type="button" class="btn-primary mt-3 w-full" @click="ignore">Bỏ qua</button>
        </Sheet>
        <PhotoViewer v-if="viewing" :photos="[{ lg: viewing }]" @close="viewing = null" />
    </AdminShell>
</template>
