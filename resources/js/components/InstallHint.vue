<script setup>
import { computed, ref } from 'vue';
import { Share, SquarePlus, X } from 'lucide-vue-next';
import { dismiss, dismissed, install, isIos, standalone } from '../lib/install.js';

// Gợi ý cài Quỹ nhóm lên màn hình chính, hiện một lần trong khu quản trị.
const hidden = ref(dismissed() || standalone());
const show = computed(() => !hidden.value && !install.installed && (install.prompt || isIos()));

function close() {
    dismiss();
    hidden.value = true;
}
async function installNow() {
    install.prompt.prompt();
    await install.prompt.userChoice.catch(() => null);
    install.prompt = null;
    close();
}
</script>

<template>
    <div v-if="show" class="card mb-3 flex items-start gap-3 border-primary/40 bg-primary/5 p-3 text-sm">
        <img src="/icons/icon-192.png" alt="" class="size-10 shrink-0 rounded-xl" />
        <div class="min-w-0 flex-1">
            <p class="font-semibold">Cài Quỹ nhóm lên điện thoại</p>
            <p v-if="install.prompt" class="text-muted-foreground">Mở nhanh như app, không cần gõ địa chỉ.</p>
            <p v-else class="text-muted-foreground">
                Bấm <Share class="inline size-4 align-text-bottom" /> <b>Chia sẻ</b> ở thanh dưới Safari, chọn <SquarePlus class="inline size-4 align-text-bottom" /> <b>Thêm vào MH chính</b>.
            </p>
            <button v-if="install.prompt" type="button" class="btn-primary mt-2 !min-h-9 !px-3 text-sm" @click="installNow">Cài đặt</button>
        </div>
        <button type="button" class="icon-btn -m-2 shrink-0" aria-label="Ẩn gợi ý" @click="close"><X class="size-4" /></button>
    </div>
</template>
