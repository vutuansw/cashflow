<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';

// Menu xổ xuống gắn vào một nút: bấm nút để mở/đóng, bấm ra ngoài hoặc Esc để đóng, chọn một mục cũng đóng.
// Slot "trigger" nhận { open, toggle }; slot mặc định chứa các mục (dùng class "menu-item"), nhận { close }.
defineProps({ label: { type: String, default: 'Mở menu' } });
const open = ref(false);
const root = ref(null);

const toggle = () => (open.value = !open.value);
const close = () => (open.value = false);

function onDocClick(e) {
    if (root.value && !root.value.contains(e.target)) close();
}
function onKey(e) {
    if (e.key === 'Escape') close();
}

watch(open, (v) => {
    const method = v ? 'addEventListener' : 'removeEventListener';
    document[method]('pointerdown', onDocClick);
    document[method]('keydown', onKey);
});
onBeforeUnmount(close);
</script>

<template>
    <div ref="root" class="relative">
        <slot name="trigger" :open="open" :toggle="toggle" />
        <Transition enter-from-class="opacity-0 -translate-y-1" enter-active-class="transition duration-100" leave-to-class="opacity-0" leave-active-class="transition duration-75">
            <div v-if="open" class="absolute right-0 top-full z-30 mt-2 w-56 overflow-hidden rounded-2xl border border-border bg-card py-1.5 shadow-xl" role="menu" :aria-label="label" @click="close">
                <slot :close="close" />
            </div>
        </Transition>
    </div>
</template>
