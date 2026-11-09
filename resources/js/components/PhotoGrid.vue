<script setup>
import { computed, ref } from 'vue';
import PhotoViewer from './PhotoViewer.vue';

// Lưới ảnh kiểu bài đăng Facebook: 1 ảnh to, 2 ảnh chia đôi, 3+ ảnh một to hai nhỏ, ảnh thứ 4 trở đi gộp "+N".
const props = defineProps({ photos: { type: Array, default: () => [] } });
const viewing = ref(null);
const shown = computed(() => props.photos.slice(0, 3));
const more = computed(() => props.photos.length - 3);
const ratio = (p) => (p.width && p.height ? Math.min(Math.max(p.width / p.height, 0.75), 1.9) : 4 / 3);
</script>

<template>
    <div v-if="photos.length" class="overflow-hidden rounded-xl">
        <button v-if="photos.length === 1" type="button" class="block w-full bg-muted" :style="{ aspectRatio: ratio(photos[0]) }" @click="viewing = 0">
            <img :src="photos[0].lg" alt="" loading="lazy" class="size-full object-cover" />
        </button>
        <div v-else-if="photos.length === 2" class="grid aspect-[2/1] grid-cols-2 gap-0.5">
            <button v-for="(p, i) in photos" :key="p.id" type="button" class="bg-muted" @click="viewing = i"><img :src="p.sm" alt="" loading="lazy" class="size-full object-cover" /></button>
        </div>
        <div v-else class="grid aspect-[3/2] grid-cols-3 grid-rows-2 gap-0.5">
            <button v-for="(p, i) in shown" :key="p.id" type="button" class="relative bg-muted" :class="{ 'col-span-2 row-span-2': i === 0 }" @click="viewing = i">
                <img :src="i === 0 ? p.lg : p.sm" alt="" loading="lazy" class="size-full object-cover" />
                <span v-if="i === 2 && more > 0" class="absolute inset-0 grid place-items-center bg-black/45 text-xl font-semibold text-white">+{{ more }}</span>
            </button>
        </div>
        <PhotoViewer v-if="viewing !== null" :photos="photos" :start="viewing" @close="viewing = null" />
    </div>
</template>
