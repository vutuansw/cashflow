<script setup>
import { useRoute, useRouter } from 'vue-router';
import { Activity, Building2, CircleDollarSign, LayoutDashboard, LogOut, ScrollText, ShieldCheck } from 'lucide-vue-next';
import { systemApi, systemSession } from '../lib/systemApi.js';

// Khung trang /system: sidebar trên máy tính, thanh menu cuộn ngang trên điện thoại (plan/06 mục 2).
defineProps({ title: String });
const route = useRoute();
const router = useRouter();
const menu = [
    { to: '/system', label: 'Tổng quan', icon: LayoutDashboard, exact: true },
    { to: '/system/nhom', label: 'Nhóm', icon: Building2 },
    { to: '/system/chi-phi-ai', label: 'Chi phí AI', icon: CircleDollarSign },
    { to: '/system/nhat-ky', label: 'Nhật ký', icon: ScrollText },
    { to: '/system/quan-tri-vien', label: 'Quản trị viên', icon: ShieldCheck },
];
const active = (item) => (item.exact ? route.path === item.to : route.path.startsWith(item.to));

async function logout() {
    await systemApi('/logout', { method: 'POST' }).catch(() => {});
    systemSession.admin = null;
    systemSession.csrf = null;
    router.push('/system/dang-nhap');
}
</script>

<template>
    <div class="min-h-dvh lg:flex">
        <aside class="sticky top-0 hidden h-dvh w-60 shrink-0 flex-col border-r border-border bg-card p-4 lg:flex">
            <p class="flex items-center gap-2 font-semibold"><Activity class="size-5 text-primary" /> Quỹ nhóm · System</p>
            <nav class="mt-6 space-y-1">
                <RouterLink v-for="item in menu" :key="item.to" :to="item.to" class="flex min-h-11 items-center gap-3 rounded-xl px-3 font-medium hover:bg-muted" :class="{ 'bg-primary/10 text-primary': active(item) }">
                    <component :is="item.icon" class="size-5" /> {{ item.label }}
                </RouterLink>
            </nav>
            <div class="mt-auto text-sm">
                <p class="truncate px-3 text-muted-foreground">{{ systemSession.admin?.email }}</p>
                <button type="button" class="mt-1 flex min-h-11 w-full items-center gap-3 rounded-xl px-3 font-medium hover:bg-muted" @click="logout"><LogOut class="size-5" /> Đăng xuất</button>
            </div>
        </aside>
        <div class="min-w-0 flex-1">
            <header class="sticky top-0 z-20 border-b border-border bg-background/90 backdrop-blur">
                <div class="flex min-h-14 items-center gap-2 px-4 lg:px-8">
                    <h1 class="min-w-0 flex-1 truncate text-lg font-semibold">{{ title }}</h1>
                    <slot name="actions" />
                    <button type="button" class="icon-btn lg:hidden" aria-label="Đăng xuất" @click="logout"><LogOut class="size-5" /></button>
                </div>
                <nav class="flex gap-1 overflow-x-auto px-3 pb-2 lg:hidden">
                    <RouterLink v-for="item in menu" :key="item.to" :to="item.to" class="chip !min-h-9 shrink-0 whitespace-nowrap !px-3 !text-sm" :class="active(item) ? '!bg-primary !text-primary-foreground' : ''">{{ item.label }}</RouterLink>
                </nav>
            </header>
            <main class="mx-auto max-w-5xl px-4 py-4 lg:px-8 lg:py-6"><slot /></main>
        </div>
    </div>
</template>
