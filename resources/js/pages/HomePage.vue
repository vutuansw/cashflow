<script setup>
import { onMounted, ref } from 'vue';
import { PiggyBank, Receipt, QrCode, Users } from 'lucide-vue-next';
import { api } from '../lib/api.js';

const app = ref({ name: 'Quỹ nhóm', description: 'Quản lý quỹ tiền thu chi của hội nhóm theo dòng thời gian.' });

const features = [
    { icon: Receipt, title: 'Thu chi theo dòng thời gian', text: 'Mỗi khoản thu chi là một bài trên timeline của nhóm, kèm ảnh sự kiện.' },
    { icon: QrCode, title: 'Đóng quỹ bằng mã QR', text: 'Chiến dịch thu có QR chuyển khoản điền sẵn nội dung, tự nhận biến động số dư qua SePay.' },
    { icon: Users, title: 'Minh bạch cho cả nhóm', text: 'Ai đã đóng, ai chưa đóng, quỹ còn bao nhiêu: xem ngay bằng một đường link.' },
];

onMounted(async () => {
    try {
        app.value = await api('/app');
    } catch {
        // Giữ nội dung mặc định khi chưa gọi được API.
    }
});
</script>

<template>
    <main class="mx-auto flex min-h-dvh max-w-xl flex-col px-4 py-10">
        <div class="flex items-center gap-3">
            <span class="grid size-12 place-items-center rounded-2xl bg-primary text-primary-foreground">
                <PiggyBank class="size-7" />
            </span>
            <h1 class="text-3xl font-semibold tracking-tight">{{ app.name }}</h1>
        </div>
        <p class="mt-4 text-lg text-muted-foreground">{{ app.description }}</p>

        <ul class="mt-8 space-y-3">
            <li v-for="item in features" :key="item.title" class="flex gap-3 rounded-2xl border border-border bg-card p-4">
                <component :is="item.icon" class="mt-0.5 size-5 shrink-0 text-primary" />
                <div>
                    <p class="font-semibold">{{ item.title }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ item.text }}</p>
                </div>
            </li>
        </ul>

        <div class="mt-auto grid gap-3 pt-10">
            <a href="/dang-ky" class="rounded-xl bg-primary px-4 py-3 text-center font-semibold text-primary-foreground hover:bg-primary-hover">Đăng ký nhóm</a>
            <a href="/dang-nhap" class="rounded-xl border border-border bg-card px-4 py-3 text-center font-semibold">Đăng nhập</a>
        </div>
    </main>
</template>
