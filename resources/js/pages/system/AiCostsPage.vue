<script setup>
import { onMounted, ref, watch } from 'vue';
import SystemShell from '../../layouts/SystemShell.vue';
import { systemApi } from '../../lib/systemApi.js';
import { formatMoney } from '../../lib/money.js';

// Lượt đọc bill và chi phí AI theo nhóm trong tháng (plan/04 mục 6).
const now = new Date();
const month = ref(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`);
const data = ref(null);
const load = async () => (data.value = await systemApi(`/ai-costs?month=${month.value}`));
onMounted(load);
watch(month, load);
</script>

<template>
    <SystemShell title="Chi phí AI">
        <input v-model="month" type="month" class="field max-w-48" aria-label="Tháng" />
        <p v-if="!data" class="py-10 text-center text-sm text-muted-foreground">Đang tải…</p>
        <template v-else>
            <p class="mt-3 text-sm">Tổng <b>{{ data.total.scans }}</b> lượt · <b>${{ data.total.cost_usd }}</b> ≈ {{ formatMoney(data.total.cost_vnd) }} <span class="text-muted-foreground">(1 USD = {{ formatMoney(data.usd_vnd) }})</span></p>
            <div class="card mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-muted-foreground"><th class="p-3 font-medium">Nhóm</th><th class="p-3 text-right font-medium">Lượt</th><th class="p-3 text-right font-medium">Đọc lại</th><th class="p-3 text-right font-medium">Lỗi</th><th class="p-3 text-right font-medium">Token</th><th class="p-3 text-right font-medium">Chi phí</th></tr></thead>
                    <tbody class="tabular-nums">
                        <tr v-for="r in data.rows" :key="r.group_id" class="border-t border-border">
                            <td class="p-3"><RouterLink :to="`/system/nhom/${r.group_id}`" class="font-medium hover:underline">{{ r.name }}</RouterLink> <span class="font-mono text-xs text-muted-foreground">/{{ r.code }}</span></td>
                            <td class="p-3 text-right">{{ r.scans }}</td>
                            <td class="p-3 text-right">{{ r.fallbacks }}</td>
                            <td class="p-3 text-right">{{ r.failed }}</td>
                            <td class="p-3 text-right">{{ r.tokens.toLocaleString('vi-VN') }}</td>
                            <td class="p-3 text-right">${{ r.cost_usd }}<br /><span class="text-xs text-muted-foreground">{{ formatMoney(r.cost_vnd) }}</span></td>
                        </tr>
                        <tr v-if="!data.rows.length"><td colspan="6" class="p-6 text-center text-muted-foreground">Tháng này chưa có lượt đọc bill nào.</td></tr>
                    </tbody>
                </table>
            </div>
        </template>
    </SystemShell>
</template>
