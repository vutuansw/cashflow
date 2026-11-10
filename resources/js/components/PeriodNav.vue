<script setup>
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

// Thanh chuyển kỳ "‹ Tháng 11/2026 ›" dùng chung (danh sách phiếu, báo cáo, thống kê). Khung (card, viền) do nơi dùng quyết định.
// hideArrows: kỳ "Tất cả" hoặc tùy chọn ngày thì không lùi/tiến được.
defineProps({
    label: { type: String, required: true },
    canPrev: { type: Boolean, default: true },
    canNext: { type: Boolean, default: true },
    hideArrows: Boolean,
});
const emit = defineEmits(['prev', 'next']);
</script>

<template>
    <div class="flex items-center justify-between gap-1">
        <button type="button" class="icon-btn !size-10 disabled:opacity-30" :class="{ invisible: hideArrows }" :disabled="!canPrev" aria-label="Kỳ trước" @click="emit('prev')"><ChevronLeft class="size-5" /></button>
        <p class="min-w-0 truncate text-center font-semibold">{{ label }}</p>
        <button type="button" class="icon-btn !size-10 disabled:opacity-30" :class="{ invisible: hideArrows }" :disabled="!canNext" aria-label="Kỳ sau" @click="emit('next')"><ChevronRight class="size-5" /></button>
    </div>
</template>
