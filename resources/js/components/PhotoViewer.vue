<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ChevronLeft, ChevronRight, X } from 'lucide-vue-next';

// Xem ảnh toàn màn hình, vuốt hoặc bấm mũi tên để qua lại, Esc để đóng.
const props = defineProps({ photos: { type: Array, default: () => [] }, start: { type: Number, default: 0 } });
const emit = defineEmits(['close']);
const index = ref(props.start);
watch(() => props.start, (v) => (index.value = v));

const go = (step) => (index.value = (index.value + step + props.photos.length) % props.photos.length);

let touchX = null;
const onTouchStart = (e) => (touchX = e.touches[0].clientX);
function onTouchEnd(e) {
    if (touchX === null) return;
    const dx = e.changedTouches[0].clientX - touchX;
    if (Math.abs(dx) > 40) go(dx < 0 ? 1 : -1);
    touchX = null;
}
function onKey(e) {
    if (e.key === 'Escape') emit('close');
    if (e.key === 'ArrowRight') go(1);
    if (e.key === 'ArrowLeft') go(-1);
}
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex flex-col bg-black text-white" role="dialog" aria-label="Xem ảnh" @touchstart.passive="onTouchStart" @touchend="onTouchEnd">
            <div class="flex items-center justify-between p-2 pt-[max(0.5rem,env(safe-area-inset-top))]">
                <span class="px-3 text-sm text-white/80">{{ index + 1 }} / {{ photos.length }}</span>
                <button type="button" class="inline-grid size-11 place-items-center rounded-xl hover:bg-white/10" aria-label="Đóng" @click="emit('close')"><X class="size-6" /></button>
            </div>
            <div class="relative flex min-h-0 flex-1 items-center justify-center">
                <img :src="photos[index]?.lg" alt="" class="max-h-full max-w-full object-contain" />
                <template v-if="photos.length > 1">
                    <button type="button" class="absolute left-2 hidden size-11 place-items-center rounded-full bg-white/15 sm:inline-grid" aria-label="Ảnh trước" @click="go(-1)"><ChevronLeft class="size-6" /></button>
                    <button type="button" class="absolute right-2 hidden size-11 place-items-center rounded-full bg-white/15 sm:inline-grid" aria-label="Ảnh sau" @click="go(1)"><ChevronRight class="size-6" /></button>
                </template>
            </div>
            <div class="h-[env(safe-area-inset-bottom)]"></div>
        </div>
    </Teleport>
</template>
