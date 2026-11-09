<script setup>
import { computed, ref } from 'vue';
import CategoryIcon from './CategoryIcon.vue';
import { formatMoney } from '../lib/money.js';

// Cơ cấu chi (hoặc thu) theo loại: vòng tròn + danh sách tên, số tiền, tỉ lệ ngay
// bên cạnh nên không phải đoán màu. Màu là màu nhóm chọn cho từng loại.
const props = defineProps({ items: { type: Array, default: () => [] }, total: Number });
const active = ref(null);
const R = 42;
const C = 2 * Math.PI * R;
const GAP = 1.2;

const arcs = computed(() => {
    let offset = 0;
    const sum = props.items.reduce((s, i) => s + i.total, 0) || 1;
    return props.items.map((item, i) => {
        const length = (item.total / sum) * C;
        const arc = { item, i, dash: `${Math.max(length - (props.items.length > 1 ? GAP : 0), 0.5)} ${C}`, offset: -offset };
        offset += length;
        return arc;
    });
});
const center = computed(() => (active.value === null ? null : props.items[active.value]));
</script>

<template>
    <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
        <svg viewBox="0 0 100 100" class="size-40 shrink-0 -rotate-90" role="img" aria-label="Biểu đồ cơ cấu theo loại">
            <circle cx="50" cy="50" :r="R" fill="none" class="stroke-muted" stroke-width="12" />
            <circle
                v-for="a in arcs"
                :key="a.item.category_id"
                cx="50" cy="50" :r="R" fill="none"
                :stroke="a.item.color || '#64748b'"
                :stroke-width="active === a.i ? 15 : 12"
                :stroke-dasharray="a.dash"
                :stroke-dashoffset="a.offset"
                class="cursor-pointer transition-all"
                @mouseenter="active = a.i" @mouseleave="active = null" @click="active = active === a.i ? null : a.i"
            />
            <g class="rotate-90" style="transform-origin: 50px 50px">
                <text x="50" y="47" text-anchor="middle" class="fill-muted-foreground text-[7px]">{{ center ? center.name : 'Tổng' }}</text>
                <text x="50" y="58" text-anchor="middle" class="fill-foreground text-[8px] font-semibold">{{ center ? `${center.percent}%` : formatMoney(total).replace(' đ', '') }}</text>
            </g>
        </svg>
        <ul class="w-full flex-1 space-y-1">
            <li v-for="(item, i) in items" :key="item.category_id" class="flex items-center gap-3 rounded-xl p-1.5" :class="{ 'bg-muted': active === i }" @mouseenter="active = i" @mouseleave="active = null">
                <CategoryIcon :name="item.icon" :color="item.color" size="size-8" />
                <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ item.name }}</span>
                <span class="text-right text-sm"><span class="block font-semibold">{{ formatMoney(item.total) }}</span><span class="text-xs text-muted-foreground">{{ item.percent }}%</span></span>
            </li>
        </ul>
    </div>
</template>
