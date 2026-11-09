<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { UserRound } from 'lucide-vue-next';
import BrandMark from '../components/BrandMark.vue';
import { adminMenu, adminTabs } from '../lib/nav.js';
import { session } from '../lib/session.js';

// Khung trang quản trị nhóm, xem plan/06 mục 2: tab đáy trên điện thoại, sidebar trên máy tính.
defineProps({ title: String });
const route = useRoute();
const code = computed(() => route.params.group);
const group = computed(() => session.me?.groups.find((g) => g.code === code.value));
const menu = computed(() => adminMenu(code.value));
const tabs = computed(() => adminTabs(code.value));

function active(item) {
    return item.exact ? route.path === item.to : route.path.startsWith(item.to);
}
</script>

<template>
    <div class="min-h-dvh lg:flex">
        <aside class="sticky top-0 hidden h-dvh w-64 shrink-0 flex-col border-r border-border bg-card p-4 lg:flex">
            <BrandMark />
            <RouterLink to="/nhom" class="mt-6 block rounded-xl bg-muted p-3">
                <span class="block truncate font-semibold">{{ group?.name }}</span>
                <span class="text-sm text-muted-foreground">{{ group?.role_label }} · Đổi nhóm</span>
            </RouterLink>
            <nav class="mt-4 space-y-1">
                <RouterLink v-for="item in menu" :key="item.to" :to="item.to" class="flex min-h-11 items-center gap-3 rounded-xl px-3 font-medium hover:bg-muted" :class="{ 'bg-primary/10 text-primary': active(item) }">
                    <component :is="item.icon" class="size-5" /> {{ item.label }}
                </RouterLink>
            </nav>
            <RouterLink to="/ca-nhan" class="mt-auto flex min-h-11 items-center gap-3 rounded-xl px-3 font-medium hover:bg-muted">
                <UserRound class="size-5" /> {{ session.me?.name }}
            </RouterLink>
        </aside>

        <div class="min-w-0 flex-1 pb-24 lg:pb-8">
            <header class="sticky top-0 z-20 flex min-h-14 items-center gap-2 border-b border-border bg-background/90 px-4 backdrop-blur lg:px-8">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs text-muted-foreground lg:hidden">{{ group?.name }}</p>
                    <h1 class="truncate text-lg font-semibold">{{ title }}</h1>
                </div>
                <slot name="actions" />
            </header>
            <main class="mx-auto max-w-4xl px-4 py-4 lg:px-8 lg:py-6"><slot /></main>
        </div>

        <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 border-t border-border bg-card pb-[env(safe-area-inset-bottom)] lg:hidden">
            <RouterLink v-for="tab in tabs" :key="tab.to" :to="tab.to" class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-xs font-medium" :class="active(tab) && !tab.primary ? 'text-primary' : 'text-muted-foreground'">
                <span v-if="tab.primary" class="-mt-5 grid size-12 place-items-center rounded-full bg-primary text-primary-foreground shadow-lg"><component :is="tab.icon" class="size-6" /></span>
                <component :is="tab.icon" v-else class="size-5" />
                <span>{{ tab.label }}</span>
            </RouterLink>
        </nav>
    </div>
</template>
