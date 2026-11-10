<script setup>
import { ScanText } from 'lucide-vue-next';
import { billReading } from '../lib/billScan.js';
</script>

<template>
    <Teleport to="body">
        <div v-if="billReading.busy" class="fixed inset-0 z-50 flex flex-col items-center justify-center gap-4 bg-background/95 p-6 text-center" role="status" aria-live="polite">
            <div class="relative h-64 w-36 overflow-hidden rounded-2xl border border-border bg-muted shadow">
                <img v-if="billReading.preview" :src="billReading.preview" alt="" class="size-full object-cover" />
                <span class="absolute inset-x-0 top-0 h-1 animate-[scan_1.6s_ease-in-out_infinite] bg-primary shadow-[0_0_12px_var(--primary)]"></span>
            </div>
            <p class="flex items-center gap-2 font-semibold"><ScanText class="size-5 text-primary" /> Đang đọc bill…</p>
            <p class="text-sm text-muted-foreground">Thường mất 3–6 giây.</p>
        </div>
    </Teleport>
</template>

<style scoped>
@keyframes scan {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(16rem); }
}
</style>
