<script setup>
import { computed } from 'vue';

// Ảnh đại diện tròn của một người; chưa có ảnh thì hiện chữ cái đầu của tên. Xem plan/08 mục 6.
// Kích thước, viền… truyền qua class (mặc định size-8).
const props = defineProps({ url: String, name: String });
const initial = computed(() => (props.name ?? '?').trim().split(/\s+/).pop()?.charAt(0)?.toUpperCase() || '?');
</script>

<template>
    <img v-if="url" :src="url" alt="" class="size-8 shrink-0 rounded-full bg-muted object-cover" />
    <span v-else class="grid size-8 shrink-0 place-items-center rounded-full bg-primary/15 font-semibold text-primary" aria-hidden="true">{{ initial }}</span>
</template>
