<script setup>
import { computed } from 'vue';

// Ảnh đại diện tròn của một người. Xem plan/08 mục 6.
// Chưa có ảnh: chữ cái đầu trên nền xám đặc (không trong suốt, để chồng lên nhau trong
// dãy avatar vẫn rõ từng vòng). Kích thước, viền… truyền qua class (mặc định size-8).
// Chữ cái lấy từ tên gọi (từ cuối): "Nguyễn Thị Lan" → L.
const props = defineProps({ url: String, name: String });
const initial = computed(() => (props.name ?? '?').trim().split(/\s+/).pop()?.charAt(0)?.toUpperCase() || '?');
</script>

<template>
    <img v-if="url" :src="url" alt="" class="size-8 shrink-0 rounded-full bg-muted object-cover" />
    <span v-else class="grid size-8 shrink-0 place-items-center rounded-full bg-avatar-empty font-semibold text-avatar-empty-foreground" aria-hidden="true">{{ initial }}</span>
</template>
