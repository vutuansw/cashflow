<script setup>
import { onMounted, ref } from 'vue';
import SystemShell from '../../layouts/SystemShell.vue';
import { systemApi } from '../../lib/systemApi.js';
import { formatMoney } from '../../lib/money.js';

const data = ref(null);
onMounted(async () => (data.value = await systemApi('/overview')));
</script>

<template>
    <SystemShell title="Tổng quan">
        <p v-if="!data" class="py-10 text-center text-sm text-muted-foreground">Đang tải…</p>
        <div v-else class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <RouterLink to="/system/groups" class="card p-4"><p class="text-sm text-muted-foreground">Nhóm</p><p class="text-2xl font-semibold">{{ data.groups.total }}</p><p class="text-xs text-muted-foreground">+{{ data.groups.new_7_days }} trong 7 ngày</p></RouterLink>
            <RouterLink to="/system/groups?review=pending_review" class="card p-4"><p class="text-sm text-muted-foreground">Chờ duyệt</p><p class="text-2xl font-semibold" :class="{ 'text-amber-600': data.groups.pending_review }">{{ data.groups.pending_review }}</p></RouterLink>
            <RouterLink to="/system/groups?service=locked" class="card p-4"><p class="text-sm text-muted-foreground">Đang khóa</p><p class="text-2xl font-semibold" :class="{ 'text-expense': data.groups.locked }">{{ data.groups.locked }}</p></RouterLink>
            <div class="card p-4"><p class="text-sm text-muted-foreground">Người dùng</p><p class="text-2xl font-semibold">{{ data.users }}</p></div>
            <div class="card p-4"><p class="text-sm text-muted-foreground">Phiếu thu chi</p><p class="text-2xl font-semibold">{{ data.transactions }}</p></div>
            <RouterLink to="/system/ai-costs" class="card col-span-2 p-4 lg:col-span-3">
                <p class="text-sm text-muted-foreground">AI đọc bill tháng này</p>
                <p class="text-2xl font-semibold">{{ data.ai_this_month.scans }} lượt · ${{ data.ai_this_month.cost_usd }}</p>
                <p class="text-xs text-muted-foreground">≈ {{ formatMoney(data.ai_this_month.cost_vnd) }}</p>
            </RouterLink>
        </div>
    </SystemShell>
</template>
