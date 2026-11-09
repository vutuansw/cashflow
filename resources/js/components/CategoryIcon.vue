<script setup>
import { computed } from 'vue';
import { icons } from 'lucide-vue-next';

// Biểu tượng loại thu chi theo tên lucide dạng kebab (piggy-bank), có màu nền nhạt của loại.
const props = defineProps({ name: String, color: String, size: { type: String, default: 'size-10' } });
const component = computed(() => {
    const pascal = (props.name || 'circle').replace(/(^|-)([a-z0-9])/g, (_, __, c) => c.toUpperCase());
    return icons[pascal] ?? icons.Circle;
});
</script>

<template>
    <span class="grid shrink-0 place-items-center rounded-xl" :class="size" :style="{ backgroundColor: `${color || '#64748b'}22`, color: color || '#64748b' }">
        <component :is="component" class="size-1/2" />
    </span>
</template>
