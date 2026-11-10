<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { EyeOff, Image, Plus, ReceiptText, RotateCcw, Trash2 } from 'lucide-vue-next';
import AdminShell from '../../layouts/AdminShell.vue';
import CategoryIcon from '../../components/CategoryIcon.vue';
import FilterBar from '../../components/FilterBar.vue';
import FilterOptions from '../../components/FilterOptions.vue';
import PeriodNav from '../../components/PeriodNav.vue';
import SegmentedControl from '../../components/SegmentedControl.vue';
import { adminApi } from '../../lib/api.js';
import { formatMoney } from '../../lib/money.js';
import { formatDateTime } from '../../lib/time.js';
import { usePeriod } from '../../lib/period.js';
import { toast } from '../../lib/notice.js';

// Danh sách phiếu thu chi: lọc theo thời gian, thu/chi, loại, nguồn, tìm theo tên; tổng của bộ lọc; thùng rác 30 ngày. Xem plan/03 mục 2.
// Bố cục như app quản lý tài chính: ô tìm + nút "Lọc" (loại, nguồn trong bảng trượt); chọn kỳ Tháng/Năm/Tất cả rồi
// lùi/tiến từng kỳ bằng ‹ ›; ô Thu/Chi trong bảng tổng bấm được để chỉ xem thu hoặc chi.
const route = useRoute();
const router = useRouter();
const code = computed(() => route.params.group);

// Bộ lọc nằm trên URL (?period=month&at=2026-11&type=...) để bấm ‹ từ màn chi tiết quay về vẫn giữ nguyên.
const q = route.query;
const period = usePeriod(q);
const filters = ref({
    type: q.type ?? '',
    category_id: q.category ?? '',
    source: q.source ?? '',
    q: q.q ?? '',
});
const trashed = ref(q.trashed === '1');

function syncQuery() {
    const f = filters.value;
    const query = Object.fromEntries(Object.entries({
        ...period.toQuery(), type: f.type, category: f.category_id, source: f.source, q: f.q, trashed: trashed.value ? '1' : '',
    }).filter(([, v]) => v !== '' && v !== null));
    router.replace({ query });
}

const setType = (t) => {
    filters.value.type = filters.value.type === t ? '' : t;
    filters.value.category_id = '';
};

const rows = ref([]);
const meta = ref(null);
const totals = ref(null);
const loading = ref(false);
const categories = ref([]);

async function load(page = 1) {
    loading.value = true;
    // Phiếu đã xóa: xem hết trong 30 ngày giữ lại, không giới hạn theo kỳ (phiếu năm ngoái xóa hôm qua vẫn phải thấy).
    const params = new URLSearchParams({ page, ...(trashed.value ? {} : period.range.value) });
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
    timer = setTimeout(() => {
        syncQuery();
        load();
    }, 250);
}, { deep: true });
watch(() => ({ ...period.state }), () => {
    syncQuery();
    load();
});
watch(trashed, () => {
    syncQuery();
    load();
});

onMounted(async () => {
    load();
    categories.value = await adminApi(code.value, '/categories').catch(() => []);
});

const shownCategories = computed(() => categories.value.filter((c) => !filters.value.type || c.type === filters.value.type));

// Bảng "Lọc": trạng thái (đang dùng / đã xóa), thu/chi, loại, nguồn. Nhãn dưới ô tìm là các điều kiện đang bật.
const sources = [{ v: 'manual', l: 'Nhập tay' }, { v: 'ai_bill', l: 'AI đọc bill' }, { v: 'sepay', l: 'SePay' }];
const categoryOptions = computed(() => [{ v: '', l: 'Mọi loại' }, ...shownCategories.value.map((c) => ({ v: c.id, l: c.name, dot: c.type }))]);
const trashedModel = computed({ get: () => (trashed.value ? '1' : ''), set: (v) => (trashed.value = v === '1') });
const typeModel = computed({ get: () => filters.value.type, set: (v) => Object.assign(filters.value, { type: v, category_id: '' }) });
const activeChips = computed(() => {
    const out = trashed.value ? [{ key: 'trashed', label: 'Đã xóa' }] : [];
    const cat = categories.value.find((c) => String(c.id) === String(filters.value.category_id));
    if (cat) out.push({ key: 'category_id', label: cat.name });
    const src = sources.find((x) => x.v === filters.value.source);
    if (src) out.push({ key: 'source', label: src.l });
    return out;
});
function removeChip(key) {
    if (key === 'trashed') trashed.value = false;
    else filters.value[key] = '';
}
function resetFilters() {
    Object.assign(filters.value, { type: '', category_id: '', source: '' });
    trashed.value = false;
}

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
            <RouterLink :to="`/${code}/admin/transactions/new?type=expense`" class="btn-primary hidden !min-h-10 sm:inline-flex"><Plus class="size-4" /> Phiếu mới</RouterLink>
        </template>

        <FilterBar v-model:q="filters.q" placeholder="Tìm tiêu đề, tên người" :chips="activeChips" :total="meta?.total ?? null" unit="phiếu" title="Lọc phiếu" @remove="removeChip" @reset="resetFilters">
            <template #below>
                <!-- Phiếu đã xóa không lọc theo kỳ nên ẩn -->
                <SegmentedControl v-if="!trashed" :model-value="period.state.kind" :options="period.options" label="Kỳ xem" @update:model-value="period.setKind" />
            </template>
            <FilterOptions v-model="trashedModel" title="Trạng thái" :options="[{ v: '', l: 'Đang dùng' }, { v: '1', l: 'Đã xóa (30 ngày)', icon: Trash2 }]" />
            <section>
                <h3 class="mb-2 text-sm font-semibold">Thu hay chi</h3>
                <SegmentedControl v-model="typeModel" :options="[{ v: '', l: 'Cả hai' }, { v: 'income', l: 'Thu' }, { v: 'expense', l: 'Chi' }]" label="Thu hay chi" />
            </section>
            <FilterOptions v-model="filters.category_id" title="Loại" :options="categoryOptions" />
            <FilterOptions v-model="filters.source" title="Nguồn" :options="[{ v: '', l: 'Mọi nguồn' }, ...sources]" />
        </FilterBar>

        <p v-if="trashed" class="mt-3 flex items-start gap-2 rounded-xl bg-muted p-3 text-sm"><Trash2 class="mt-0.5 size-4 shrink-0 text-muted-foreground" /> Phiếu đã xóa giữ lại 30 ngày, khôi phục được. Quá hạn sẽ xóa hẳn.</p>

        <!-- Bảng tổng của kỳ đang xem; ô Thu/Chi bấm được để chỉ xem thu hoặc chi -->
        <section v-if="!trashed" class="card mt-3 overflow-hidden">
            <PeriodNav class="border-b border-border p-1" :label="period.label.value" :can-next="period.canNext.value" :hide-arrows="period.state.kind === 'all'" @prev="period.shift(-1)" @next="period.shift(1)" />
            <div class="grid grid-cols-3 divide-x divide-border text-center text-sm">
                <button type="button" class="p-3 transition" :class="filters.type === 'income' ? 'bg-income/10' : 'hover:bg-muted/60'" :aria-pressed="filters.type === 'income'" @click="setType('income')">
                    <p class="text-xs text-muted-foreground">Thu</p>
                    <p class="font-semibold tabular-nums text-income">{{ totals ? formatMoney(totals.income).replace(' đ', '') : '…' }}</p>
                </button>
                <button type="button" class="p-3 transition" :class="filters.type === 'expense' ? 'bg-expense/10' : 'hover:bg-muted/60'" :aria-pressed="filters.type === 'expense'" @click="setType('expense')">
                    <p class="text-xs text-muted-foreground">Chi</p>
                    <p class="font-semibold tabular-nums text-expense">{{ totals ? formatMoney(totals.expense).replace(' đ', '') : '…' }}</p>
                </button>
                <div class="p-3">
                    <p class="text-xs text-muted-foreground">Chênh lệch</p>
                    <p class="font-semibold tabular-nums">{{ totals ? formatMoney(totals.income - totals.expense).replace(' đ', '') : '…' }}</p>
                </div>
            </div>
        </section>

        <ul class="card mt-3 divide-y divide-border">
            <li v-for="row in rows" :key="row.id">
                <component :is="trashed ? 'div' : 'RouterLink'" :to="trashed ? undefined : `/${code}/admin/transactions/${row.id}`" class="flex items-center gap-3 p-3" :class="{ 'hover:bg-muted/60': !trashed }">
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
                    <!-- Phiếu đã xóa: số tiền và nút khôi phục xếp dọc để tên phiếu còn chỗ trên điện thoại -->
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <p class="font-semibold tabular-nums" :class="row.type === 'income' ? 'text-income' : 'text-expense'">{{ row.type === 'income' ? '+' : '−' }}{{ formatMoney(row.amount).replace(' đ', '') }}</p>
                        <button v-if="trashed" type="button" class="inline-flex min-h-8 items-center gap-1 rounded-full bg-primary/10 px-3 text-sm font-medium text-primary hover:bg-primary/15" @click="restore(row)"><RotateCcw class="size-3.5" /> Khôi phục</button>
                    </div>
                </component>
            </li>
            <li v-if="!loading && !rows.length" class="p-6 text-center text-sm text-muted-foreground">
                {{ trashed ? 'Không có phiếu nào đã xóa.' : 'Chưa có phiếu nào khớp bộ lọc.' }}
                <RouterLink v-if="!trashed" :to="`/${code}/admin/transactions/new?type=expense`" class="mt-2 block font-semibold text-primary">Tạo phiếu đầu tiên</RouterLink>
            </li>
        </ul>

        <div class="py-4 text-center">
            <p v-if="loading" class="text-sm text-muted-foreground">Đang tải…</p>
            <button v-else-if="meta && meta.current_page < meta.last_page" type="button" class="btn-outline" @click="load(meta.current_page + 1)">Xem thêm</button>
            <p v-else-if="meta && rows.length" class="text-xs text-muted-foreground">{{ meta.total }} phiếu</p>
        </div>

    </AdminShell>
</template>
