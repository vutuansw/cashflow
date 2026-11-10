<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { ChevronRight, RefreshCw, UserRound } from 'lucide-vue-next';
import AdminShell from '../../layouts/AdminShell.vue';
import { adminMenu } from '../../lib/nav.js';
import { pending } from '../../lib/pending.js';

// Trang "Thêm" trên điện thoại: các mục không nằm trên thanh tab đáy.
const route = useRoute();
const items = computed(() => [
    ...adminMenu(route.params.group).filter((i) => !i.tab),
    { to: '/profile', label: 'Cá nhân, đổi mật khẩu', icon: UserRound },
    { to: '/groups', label: 'Đổi nhóm', icon: RefreshCw },
]);
</script>

<template>
    <AdminShell title="Thêm">
        <nav class="card divide-y divide-border">
            <RouterLink v-for="item in items" :key="item.to" :to="item.to" class="flex min-h-14 items-center gap-3 px-4 font-medium">
                <component :is="item.icon" class="size-5 text-primary" />
                <span class="flex-1">{{ item.label }}</span>
                <span v-if="item.to.endsWith('/contacts') && pending.joins" class="rounded-full bg-expense px-1.5 text-xs font-semibold text-white">{{ pending.joins }}</span>
                <ChevronRight class="size-5 text-muted-foreground" />
            </RouterLink>
        </nav>
    </AdminShell>
</template>
