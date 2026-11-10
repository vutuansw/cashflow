<script setup>
import { ChevronRight } from 'lucide-vue-next';

// Đường dẫn các cấp cha trên máy tính, vd "Chiến dịch › Thu quỹ năm 2026". Màn hiện tại là tiêu đề trang.
defineProps({ crumbs: { type: Array, default: () => [] } });
</script>

<template>
    <nav aria-label="Đường dẫn" class="flex min-w-0 items-center gap-1 text-sm text-muted-foreground">
        <template v-for="(c, i) in crumbs" :key="c.to">
            <ChevronRight v-if="i > 0" class="size-3.5 shrink-0" />
            <RouterLink :to="c.to" class="truncate hover:text-foreground hover:underline">{{ c.label }}</RouterLink>
        </template>
    </nav>
</template>
