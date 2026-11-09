<script setup>
import { computed, ref } from 'vue';
import { formatMoney, formatMoneyShort } from '../lib/money.js';

// Cột thu/chi theo tháng, một trục. Bấm/rê vào tháng để xem số; có chú thích và
// bảng số đọc được bằng trình đọc màn hình. Màu --chart-* đã qua kiểm tra mù màu.
const props = defineProps({
    months: { type: Array, default: () => [] }, // [{ month, income, expense, label? }]
    height: { type: String, default: 'h-20' },
    labelYear: Number,
});
const active = ref(null);
const max = computed(() => Math.max(1, ...props.months.flatMap((m) => [m.income, m.expense])));
const pct = (v) => (v > 0 ? `${Math.max(3, (v / max.value) * 100)}%` : '0');
const current = computed(() => (active.value === null ? null : props.months[active.value]));
</script>

<template>
    <div>
        <div class="mb-2 flex min-h-5 items-center justify-between gap-2 text-xs text-muted-foreground">
            <span class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1"><span class="size-2.5 rounded-sm bg-chart-income"></span>Thu</span>
                <span class="inline-flex items-center gap-1"><span class="size-2.5 rounded-sm bg-chart-expense"></span>Chi</span>
            </span>
            <span v-if="current" class="font-medium text-foreground">T{{ current.month }}: +{{ formatMoneyShort(current.income) }} / −{{ formatMoneyShort(current.expense) }}</span>
        </div>
        <div class="flex items-end gap-1.5" :class="height" aria-hidden="true" @mouseleave="active = null">
            <button
                v-for="(m, i) in months"
                :key="m.month + '-' + i"
                type="button"
                tabindex="-1"
                class="flex h-full flex-1 items-end justify-center gap-0.5 rounded-md px-0.5 transition"
                :class="active === i ? 'bg-muted' : ''"
                @mouseenter="active = i"
                @click="active = active === i ? null : i"
            >
                <span class="w-1/2 max-w-3 rounded-t-[4px] bg-chart-income" :style="{ height: pct(m.income) }"></span>
                <span class="w-1/2 max-w-3 rounded-t-[4px] bg-chart-expense" :style="{ height: pct(m.expense) }"></span>
            </button>
        </div>
        <div class="mt-1 flex gap-1.5 border-t border-border pt-1 text-[10px] text-muted-foreground" aria-hidden="true">
            <span v-for="(m, i) in months" :key="i" class="flex-1 text-center">T{{ m.month }}</span>
        </div>
        <table class="sr-only">
            <caption>Thu chi theo tháng{{ labelYear ? ` năm ${labelYear}` : '' }}</caption>
            <tr><th>Tháng</th><th>Thu</th><th>Chi</th></tr>
            <tr v-for="(m, i) in months" :key="i"><td>{{ m.month }}</td><td>{{ formatMoney(m.income) }}</td><td>{{ formatMoney(m.expense) }}</td></tr>
        </table>
    </div>
</template>
