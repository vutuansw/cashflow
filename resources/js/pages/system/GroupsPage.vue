<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { Plus, Search } from 'lucide-vue-next';
import SystemShell from '../../layouts/SystemShell.vue';
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
</script>

<template>
    <SystemShell title="Nhóm">
        <template #actions><RouterLink to="/system/nhom/moi" class="btn-primary !min-h-10 !px-3"><Plus class="size-4" /> Tạo nhóm</RouterLink></template>
        <div class="grid gap-2 sm:grid-cols-[1fr_auto_auto_auto]">
            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input v-model="f.q" type="search" class="field pl-9" placeholder="Mã, tên nhóm hoặc SĐT trưởng nhóm" />
            </div>
            <select v-model="f.review" class="field sm:w-36"><option value="">Mọi trạng thái duyệt</option><option value="pending_review">Chờ duyệt</option><option value="approved">Đã duyệt</option><option value="rejected">Từ chối</option></select>
            <select v-model="f.service" class="field sm:w-32"><option value="">Mọi dịch vụ</option><option value="active">Hoạt động</option><option value="locked">Đang khóa</option></select>
            <label class="flex min-h-11 items-center gap-2 text-sm"><input v-model="f.deleted" type="checkbox" class="size-4" /> Đã xóa</label>
        </div>
        <ul class="card mt-3 divide-y divide-border">
            <li v-for="g in rows ?? []" :key="g.id">
                <RouterLink :to="`/system/nhom/${g.id}`" class="flex items-center gap-3 p-3 hover:bg-muted/50">
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
