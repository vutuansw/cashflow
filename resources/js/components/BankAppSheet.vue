<script setup>
import { computed, ref } from 'vue';
import Sheet from './Sheet.vue';
import { toast } from '../lib/notice.js';

// Lưới app ngân hàng mở bằng deeplink VietQR (plan/05 mục 5b). App "Tự điền sẵn" nhận đủ
// số tài khoản, số tiền, nội dung; app khác chỉ mở được app nên chép sẵn nội dung trước.
const props = defineProps({ open: Boolean, apps: Array, content: String });
const emit = defineEmits(['close']);

const KEY = 'quynhom:bank-app';
const last = ref(read());

function read() {
    try {
        return localStorage.getItem(KEY);
    } catch {
        return null;
    }
}

// App đã chọn lần trước lên đầu, còn lại giữ thứ tự phổ biến máy chủ trả về.
const sorted = computed(() => {
    const list = [...(props.apps ?? [])];
    const i = list.findIndex((a) => a.id === last.value);
    if (i > 0) list.unshift(...list.splice(i, 1));
    return list;
});

async function openApp(app) {
    try {
        localStorage.setItem(KEY, app.id);
    } catch {
        // Trình duyệt chặn lưu: lần sau không nhớ app, không sao.
    }
    last.value = app.id;

    if (!app.autofill && props.content) {
        try {
            await navigator.clipboard.writeText(props.content);
            toast('Đã chép nội dung, dán vào ô nội dung chuyển khoản.');
        } catch {
            toast(`Nhớ ghi nội dung: ${props.content}`);
        }
        // Đợi một chút cho người dùng kịp đọc nhắc trước khi chuyển sang app.
        setTimeout(() => (location.href = app.url), 900);
        return;
    }
    location.href = app.url;
}
</script>

<template>
    <Sheet :open="open" title="Chọn app ngân hàng" @close="emit('close')">
        <p class="text-sm text-muted-foreground">App có nhãn <b class="text-income">Tự điền</b> điền sẵn số tài khoản, số tiền và nội dung. App khác: nội dung được chép sẵn, bạn dán vào ô nội dung rồi gõ thêm tên.</p>
        <ul class="mt-4 grid grid-cols-4 gap-x-2 gap-y-4">
            <li v-for="app in sorted" :key="app.id">
                <a :href="app.url" class="flex flex-col items-center gap-1 text-center" @click.prevent="openApp(app)">
                    <span class="relative">
                        <img v-if="app.logo" :src="app.logo" :alt="''" loading="lazy" class="size-14 rounded-2xl border border-border bg-white object-contain" />
                        <span v-else class="grid size-14 place-items-center rounded-2xl bg-muted text-xs font-semibold uppercase">{{ app.id.slice(0, 3) }}</span>
                        <span v-if="app.id === last" class="absolute -right-1 -top-1 size-3 rounded-full border-2 border-card bg-primary" aria-label="Dùng lần trước"></span>
                    </span>
                    <span class="line-clamp-2 text-xs leading-tight">{{ app.name }}</span>
                    <span v-if="app.autofill" class="rounded-full bg-income/15 px-1.5 text-[10px] font-semibold text-income">Tự điền</span>
                </a>
            </li>
        </ul>
    </Sheet>
</template>
