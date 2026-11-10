<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { Plus, Trash2 } from 'lucide-vue-next';
import SystemShell from '../../layouts/SystemShell.vue';
import FilterBar from '../../components/FilterBar.vue';
import FilterOptions from '../../components/FilterOptions.vue';
import { systemApi } from '../../lib/systemApi.js';
import { formatDate } from '../../lib/time.js';

// Danh sách nhóm: lọc trạng thái duyệt/dịch vụ, tìm theo mã, tên, SĐT trưởng nhóm (plan/02 mục 6).
const route = useRoute();
const f = reactive({ q: '', review: route.query.review ?? '', service: route.query.service ?? '', deleted: false });
const rows = ref(null);
const meta = ref(null);

async function load(page = 1) {
    const params = new URLSearchParams({ page });
    for (const [k, v] of Object.entries(f)) if (v) params.set(k, v === true ? 1 : v);
    const r = await systemApi(`/groups?${params}`);
    rows.value = page === 1 ? r.data : [...rows.value, ...r.data];
    meta.value = r.meta;
}
let timer;
watch(f, () => {
    clearTimeout(timer);
    timer = setTimeout(load, 250);
});
onMounted(load);

const reviewLabel = { pending_review: 'Chờ duyệt', approved: 'Đã duyệt', rejected: 'Từ chối' };
const serviceLabel = { active: 'Hoạt động', locked: 'Đang khóa' };

// Bộ lọc dùng chung (FilterBar): ô tìm + bảng "Lọc" (duyệt, dịch vụ, đã xóa), nhãn điều kiện đang bật bên dưới.
const deletedModel = computed({ get: () => (f.deleted ? '1' : ''), set: (v) => (f.deleted = v === '1') });
const chips = computed(() => [
    f.review && { key: 'review', label: reviewLabel[f.review] },
    f.service && { key: 'service', label: serviceLabel[f.service] },
    f.deleted && { key: 'deleted', label: 'Đã xóa' },
].filter(Boolean));
const removeChip = (key) => (f[key] = key === 'deleted' ? false : '');
const resetFilters = () => Object.assign(f, { review: '', service: '', deleted: false });
</script>

<template>
    <SystemShell title="Nhóm">
        <template #actions><RouterLink to="/system/groups/new" class="btn-primary !min-h-10 !px-3"><Plus class="size-4" /> Tạo nhóm</RouterLink></template>
        <FilterBar v-model:q="f.q" placeholder="Mã, tên nhóm hoặc SĐT trưởng nhóm" :chips="chips" :total="meta?.total ?? null" unit="nhóm" title="Lọc nhóm" @remove="removeChip" @reset="resetFilters">
            <FilterOptions v-model="f.review" title="Trạng thái duyệt" :options="[{ v: '', l: 'Tất cả' }, ...Object.entries(reviewLabel).map(([v, l]) => ({ v, l }))]" />
            <FilterOptions v-model="f.service" title="Dịch vụ" :options="[{ v: '', l: 'Tất cả' }, ...Object.entries(serviceLabel).map(([v, l]) => ({ v, l }))]" />
            <FilterOptions v-model="deletedModel" title="Nhóm đã xóa" :options="[{ v: '', l: 'Ẩn' }, { v: '1', l: 'Chỉ xem nhóm đã xóa', icon: Trash2 }]" />
        </FilterBar>
        <ul class="card mt-3 divide-y divide-border">
            <li v-for="g in rows ?? []" :key="g.id">
                <RouterLink :to="`/system/groups/${g.id}`" class="flex items-center gap-3 p-3 hover:bg-muted/50">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ g.name }} <span class="font-mono text-xs text-muted-foreground">/{{ g.code }}</span></p>
                        <p class="text-xs text-muted-foreground">{{ g.owner_name }} · tạo {{ formatDate(g.created_at) }} · {{ g.visibility === 'public' ? 'công khai' : 'riêng tư' }}</p>
                    </div>
                    <span v-if="g.deleted_at" class="chip !bg-expense/15 !text-expense">Đã xóa</span>
                    <span v-else-if="g.service_status === 'locked'" class="chip !bg-expense/15 !text-expense">Khóa</span>
                    <span class="chip" :class="{ '!bg-amber-100 !text-amber-800': g.review_status === 'pending_review', '!bg-primary/15 !text-primary': g.review_status === 'approved' }">{{ reviewLabel[g.review_status] }}</span>
                </RouterLink>
            </li>
            <li v-if="rows && !rows.length" class="p-6 text-center text-sm text-muted-foreground">Không có nhóm nào.</li>
        </ul>
        <div class="py-4 text-center">
            <button v-if="meta && meta.current_page < meta.last_page" type="button" class="btn-outline" @click="load(meta.current_page + 1)">Xem thêm</button>
            <p v-else-if="meta" class="text-xs text-muted-foreground">{{ meta.total }} nhóm</p>
        </div>
    </SystemShell>
</template>
