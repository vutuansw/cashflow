<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { EyeOff, Image, Plus, ReceiptText, RotateCcw, Search, Trash2 } from 'lucide-vue-next';
import AdminShell from '../../layouts/AdminShell.vue';
import CategoryIcon from '../../components/CategoryIcon.vue';
import { adminApi } from '../../lib/api.js';
import { formatMoney } from '../../lib/money.js';
import { formatDateTime } from '../../lib/time.js';
import { toast } from '../../lib/notice.js';

// Danh sách phiếu thu chi: lọc theo thời gian, thu/chi, loại, nguồn, tìm theo tên; tổng của bộ lọc; thùng rác 30 ngày. Xem plan/03 mục 2.
const route = useRoute();
const code = computed(() => route.params.group);

const periods = [
    { v: 'month', l: 'Tháng này' },
    { v: 'year', l: 'Năm nay' },
    { v: 'all', l: 'Tất cả' },
];
const filters = ref({ period: 'year', type: '', category_id: '', source: '', q: '' });
const trashed = ref(false);
const rows = ref([]);
const meta = ref(null);
const totals = ref(null);
const loading = ref(false);
const categories = ref([]);

function range() {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    if (filters.value.period === 'month') return { from: `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01` };
    if (filters.value.period === 'year') return { from: `${now.getFullYear()}-01-01` };
    return {};
}

async function load(page = 1) {
    loading.value = true;
    const params = new URLSearchParams({ page, ...range() });
    for (const key of ['type', 'category_id', 'source', 'q']) if (filters.value[key]) params.set(key, filters.value[key]);
    if (trashed.value) params.set('trashed', 1);
    try {
        const r = await adminApi(code.value, `/transactions?${params}`);
        rows.value = page === 1 ? r.data : [...rows.value, ...r.data];
        meta.value = r.meta;
        totals.value = r.totals;
    } finally {
        loading.value = false;
    }
}

let timer;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => load(), 250);
}, { deep: true });
watch(trashed, () => load());

onMounted(async () => {
    load();
    categories.value = await adminApi(code.value, '/categories').catch(() => []);
});

const shownCategories = computed(() => categories.value.filter((c) => !filters.value.type || c.type === filters.value.type));

async function restore(row) {
    await adminApi(code.value, `/transactions/${row.id}/restore`, { method: 'POST' });
    rows.value = rows.value.filter((r) => r.id !== row.id);
    toast('Đã khôi phục phiếu.');
}

const sourceLabel = { manual: 'Nhập tay', ai_bill: 'AI đọc bill', sepay: 'SePay' };
</script>

<template>
    <AdminShell :title="trashed ? 'Phiếu đã xóa' : 'Phiếu thu chi'">
        <template #actions>
            <button type="button" class="icon-btn" :class="{ 'bg-muted text-expense': trashed }" :aria-pressed="trashed" :aria-label="trashed ? 'Về danh sách phiếu' : 'Xem phiếu đã xóa'" @click="trashed = !trashed"><Trash2 class="size-5" /></button>
            <RouterLink :to="`/${code}/quan-tri/phieu/moi?loai=chi`" class="btn-primary hidden !min-h-10 sm:inline-flex"><Plus class="size-4" /> Phiếu mới</RouterLink>
        </template>

        <p v-if="trashed" class="mb-3 rounded-xl bg-muted p-3 text-sm">Phiếu đã xóa giữ lại 30 ngày, khôi phục được. Quá hạn sẽ xóa hẳn.</p>

        <div class="space-y-2">
            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input v-model="filters.q" type="search" class="field pl-9" placeholder="Tìm theo tiêu đề, tên người" />
            </div>
            <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 text-sm">
                <button v-for="p in periods" :key="p.v" type="button" class="chip !min-h-9 shrink-0 !px-3.5 !text-sm" :class="filters.period === p.v ? '!bg-primary !text-primary-foreground' : ''" @click="filters.period = p.v">{{ p.l }}</button>
                <select v-model="filters.type" class="chip !min-h-9 shrink-0 !px-3 !text-sm" aria-label="Thu hay chi" @change="filters.category_id = ''">
                    <option value="">Thu và chi</option><option value="income">Chỉ thu</option><option value="expense">Chỉ chi</option>
                </select>
                <select v-model="filters.category_id" class="chip !min-h-9 shrink-0 !px-3 !text-sm" aria-label="Loại">
                    <option value="">Mọi loại</option>
                    <option v-for="c in shownCategories" :key="c.id" :value="c.id">{{ c.type === 'income' ? 'Thu' : 'Chi' }} · {{ c.name }}</option>
                </select>
                <select v-model="filters.source" class="chip !min-h-9 shrink-0 !px-3 !text-sm" aria-label="Nguồn">
                    <option value="">Mọi nguồn</option><option value="manual">Nhập tay</option><option value="ai_bill">AI đọc bill</option><option value="sepay">SePay</option>
                </select>
            </div>
        </div>

        <section v-if="totals && !trashed" class="card mt-3 grid grid-cols-3 divide-x divide-border text-center text-sm">
            <div class="p-3"><p class="text-xs text-muted-foreground">Thu</p><p class="font-semibold tabular-nums text-income">{{ formatMoney(totals.income).replace(' đ', '') }}</p></div>
            <div class="p-3"><p class="text-xs text-muted-foreground">Chi</p><p class="font-semibold tabular-nums text-expense">{{ formatMoney(totals.expense).replace(' đ', '') }}</p></div>
            <div class="p-3"><p class="text-xs text-muted-foreground">Chênh lệch</p><p class="font-semibold tabular-nums">{{ formatMoney(totals.income - totals.expense).replace(' đ', '') }}</p></div>
        </section>

        <ul class="card mt-3 divide-y divide-border">
            <li v-for="row in rows" :key="row.id">
                <component :is="trashed ? 'div' : 'RouterLink'" :to="trashed ? undefined : `/${code}/quan-tri/phieu/${row.id}`" class="flex items-center gap-3 p-3" :class="{ 'hover:bg-muted/60': !trashed }">
                    <CategoryIcon :name="row.category?.icon" :color="row.category?.color" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ row.title }}</p>
                        <p class="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                            <span>{{ formatDateTime(row.occurred_at) }}</span>
                            <span v-if="row.campaign">· {{ row.campaign }}</span>
                            <span v-if="row.source !== 'manual'">· {{ sourceLabel[row.source] }}</span>
                            <span v-if="row.photos_count" class="inline-flex items-center gap-0.5"><Image class="size-3" />{{ row.photos_count }}</span>
                            <span v-if="row.bills_count" class="inline-flex items-center gap-0.5"><ReceiptText class="size-3" />bill</span>
                            <span v-if="!row.show_on_timeline" class="inline-flex items-center gap-0.5"><EyeOff class="size-3" />ẩn</span>
                        </p>
                    </div>
                    <p class="shrink-0 font-semibold tabular-nums" :class="row.type === 'income' ? 'text-income' : 'text-expense'">{{ row.type === 'income' ? '+' : '−' }}{{ formatMoney(row.amount).replace(' đ', '') }}</p>
                    <button v-if="trashed" type="button" class="btn-outline !min-h-10 !px-3 text-sm" @click="restore(row)"><RotateCcw class="size-4" /> Khôi phục</button>
                </component>
            </li>
            <li v-if="!loading && !rows.length" class="p-6 text-center text-sm text-muted-foreground">
                {{ trashed ? 'Không có phiếu nào đã xóa.' : 'Chưa có phiếu nào khớp bộ lọc.' }}
                <RouterLink v-if="!trashed" :to="`/${code}/quan-tri/phieu/moi?loai=chi`" class="mt-2 block font-semibold text-primary">Tạo phiếu đầu tiên</RouterLink>
            </li>
        </ul>

        <div class="py-4 text-center">
            <p v-if="loading" class="text-sm text-muted-foreground">Đang tải…</p>
            <button v-else-if="meta && meta.current_page < meta.last_page" type="button" class="btn-outline" @click="load(meta.current_page + 1)">Xem thêm</button>
            <p v-else-if="meta && rows.length" class="text-xs text-muted-foreground">{{ meta.total }} phiếu</p>
        </div>
    </AdminShell>
</template>
