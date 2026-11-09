<script setup>
import { X } from 'lucide-vue-next';

// Bảng trượt từ đáy trên điện thoại, hộp thoại giữa màn hình trên máy tính.
defineProps({ open: Boolean, title: String });
const emit = defineEmits(['close']);
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-40 flex items-end justify-center bg-black/40 sm:items-center" @click.self="emit('close')">
            <div class="max-h-[90dvh] w-full overflow-y-auto rounded-t-3xl bg-card p-4 pb-[max(1rem,env(safe-area-inset-bottom))] shadow-xl sm:max-w-lg sm:rounded-3xl">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold">{{ title }}</h2>
                    <button type="button" class="icon-btn" aria-label="Đóng" @click="emit('close')"><X class="size-5" /></button>
                </div>
                <slot />
            </div>
        </div>
    </Teleport>
</template>
