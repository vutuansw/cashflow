<script setup>
import { notice } from '../lib/notice.js';
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-20 z-50 flex flex-col items-center gap-2 px-4 sm:bottom-6">
        <div
            v-for="item in notice.items"
            :key="item.id"
            class="pointer-events-auto max-w-md rounded-xl px-4 py-3 text-sm font-medium shadow-lg"
            :class="item.type === 'error' ? 'bg-expense text-white' : 'bg-foreground text-background'"
            role="status"
        >
            {{ item.text }}
        </div>
    </div>
</template>
