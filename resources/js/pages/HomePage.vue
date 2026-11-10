<script setup>
import { PiggyBank, Receipt, QrCode, Users } from 'lucide-vue-next';
import { homePath, session } from '../lib/session.js';

const features = [
    { icon: Receipt, title: 'Thu chi theo dòng thời gian', text: 'Mỗi khoản thu chi là một bài trên timeline của nhóm, kèm ảnh sự kiện.' },
    { icon: QrCode, title: 'Đóng quỹ bằng mã QR', text: 'Chiến dịch thu có QR chuyển khoản điền sẵn nội dung, tự nhận biến động số dư qua SePay.' },
    { icon: Users, title: 'Minh bạch cho cả nhóm', text: 'Ai đã đóng, ai chưa đóng, quỹ còn bao nhiêu: xem ngay bằng một đường link.' },
];
</script>

<template>
    <main class="mx-auto flex min-h-dvh max-w-xl flex-col px-4 py-10">
        <div class="flex items-center gap-3">
            <span class="grid size-12 place-items-center rounded-2xl bg-primary text-primary-foreground">
                <PiggyBank class="size-7" />
            </span>
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">WePo</h1>
                <p class="text-sm font-medium text-primary">Quỹ nhóm minh bạch cho hội nhóm</p>
            </div>
        </div>
        <p class="mt-4 text-lg text-muted-foreground">Quản lý quỹ tiền thu chi của hội nhóm theo dòng thời gian.</p>

        <ul class="mt-8 space-y-3">
            <li v-for="item in features" :key="item.title" class="card flex gap-3 p-4">
                <component :is="item.icon" class="mt-0.5 size-5 shrink-0 text-primary" />
                <div>
                    <p class="font-semibold">{{ item.title }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ item.text }}</p>
                </div>
            </li>
        </ul>

        <div class="mt-auto grid gap-3 pt-10">
            <RouterLink v-if="session.me" :to="homePath()" class="btn-primary">Vào nhóm của tôi</RouterLink>
            <template v-else>
                <RouterLink to="/dang-ky" class="btn-primary">Đăng ký nhóm</RouterLink>
                <RouterLink to="/dang-nhap" class="btn-outline">Đăng nhập</RouterLink>
            </template>
        </div>
    </main>
</template>
