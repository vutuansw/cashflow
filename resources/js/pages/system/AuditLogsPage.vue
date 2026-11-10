<script setup>
import { onMounted, ref } from 'vue';
import SystemShell from '../../layouts/SystemShell.vue';
import { systemApi } from '../../lib/systemApi.js';
import { formatDateTime } from '../../lib/time.js';

// Nhật ký thao tác của quản trị /system (admin_audit_logs).
const rows = ref(null);
const meta = ref(null);
async function load(page = 1) {
    const r = await systemApi(`/audit-logs?page=${page}`);
    rows.value = page === 1 ? r.data : [...rows.value, ...r.data];
    meta.value = r.meta;
}
onMounted(load);
const labels = {
    approve: 'Duyệt nhóm', reject: 'Từ chối nhóm', unreview: 'Bỏ duyệt', lock: 'Khóa nhóm', unlock: 'Mở khóa nhóm',
    update_group: 'Sửa nhóm', change_code: 'Đổi mã nhóm', delete_group: 'Xóa nhóm', restore_group: 'Khôi phục nhóm',
    create_group: 'Tạo nhóm', create_admin: 'Thêm quản trị viên', lock_admin: 'Khóa quản trị viên', unlock_admin: 'Mở khóa quản trị viên',
};
const detail = (p) => (p?.reason ? `Lý do: ${p.reason}` : p?.after?.code && p?.before?.code !== p?.after?.code ? `${p.before.code} → ${p.after.code}` : p?.email ?? '');
</script>

<template>
    <SystemShell title="Nhật ký">
        <ul class="card divide-y divide-border text-sm">
            <li v-for="l in rows ?? []" :key="l.id" class="flex flex-wrap items-baseline gap-x-3 p-3">
                <span class="font-medium">{{ labels[l.action] ?? l.action }}</span>
                <RouterLink v-if="l.group_id" :to="`/system/nhom/${l.group_id}`" class="font-mono text-primary">/{{ l.group_code ?? l.group_id }}</RouterLink>
                <span class="text-muted-foreground">{{ detail(l.payload) }}</span>
                <span class="ml-auto text-xs text-muted-foreground">{{ l.admin ?? 'hệ thống' }} · {{ formatDateTime(l.created_at) }} · {{ l.ip }}</span>
            </li>
            <li v-if="rows && !rows.length" class="p-6 text-center text-muted-foreground">Chưa có thao tác nào.</li>
        </ul>
        <div class="py-4 text-center"><button v-if="meta && meta.current_page < meta.last_page" type="button" class="btn-outline" @click="load(meta.current_page + 1)">Xem thêm</button></div>
    </SystemShell>
</template>
