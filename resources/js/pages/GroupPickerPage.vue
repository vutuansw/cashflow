<script setup>
import { useRouter } from 'vue-router';
import { ChevronRight, Clock, LogOut, Plus, Lock, Globe } from 'lucide-vue-next';
import BrandMark from '../components/BrandMark.vue';
import UserAvatar from '../components/UserAvatar.vue';
import { logout, session } from '../lib/session.js';

const router = useRouter();

async function signOut() {
    await logout();
    router.replace('/login');
}
</script>

<template>
    <main class="mx-auto min-h-dvh max-w-md px-4 py-6">
        <div class="flex items-center justify-between">
            <BrandMark />
            <div class="flex">
                <RouterLink to="/profile" class="icon-btn" aria-label="Cá nhân"><UserAvatar :url="session.me?.avatar_url" :name="session.me?.name" class="!size-8 text-sm" /></RouterLink>
                <button class="icon-btn" aria-label="Đăng xuất" @click="signOut"><LogOut class="size-5" /></button>
            </div>
        </div>

        <h1 class="mt-8 text-2xl font-semibold tracking-tight">Chào {{ session.me?.name }}</h1>
        <p class="mt-1 text-muted-foreground">Chọn nhóm để vào.</p>

        <ul class="mt-6 space-y-3">
            <li v-for="group in session.me?.groups" :key="group.code">
                <RouterLink :to="`/${group.code}`" class="card flex items-center gap-3 p-4 hover:bg-muted">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-lg font-semibold text-primary">{{ group.name.charAt(0) }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-semibold">{{ group.name }}</span>
                        <span class="flex items-center gap-1.5 text-sm text-muted-foreground">
                            <component :is="group.visibility === 'private' ? Lock : Globe" class="size-3.5" />
                            {{ group.role_label }}<span v-if="group.locked" class="text-expense"> · Đang tạm khóa</span>
                        </span>
                    </span>
                    <ChevronRight class="size-5 text-muted-foreground" />
                </RouterLink>
            </li>
        </ul>
        <!-- Nhóm đã xin vào qua link, đang chờ duyệt (plan/13) -->
        <template v-if="session.me?.pending_groups?.length">
            <h2 class="mt-6 text-sm font-semibold text-muted-foreground">Đang chờ duyệt</h2>
            <ul class="mt-2 space-y-3">
                <li v-for="group in session.me.pending_groups" :key="group.code">
                    <RouterLink :to="`/${group.code}`" class="card flex items-center gap-3 p-4 hover:bg-muted">
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-muted text-lg font-semibold text-muted-foreground">{{ group.name.charAt(0) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{ group.name }}</span>
                            <span class="flex items-center gap-1.5 text-sm text-muted-foreground"><Clock class="size-3.5" /> Chờ quản trị viên duyệt</span>
                        </span>
                        <ChevronRight class="size-5 text-muted-foreground" />
                    </RouterLink>
                </li>
            </ul>
        </template>
        <p v-if="!session.me?.groups.length && !session.me?.pending_groups?.length" class="card mt-6 p-4 text-muted-foreground">Bạn chưa thuộc nhóm nào. Tạo nhóm mới, hoặc gửi số điện thoại của bạn cho trưởng nhóm để được thêm vào nhóm.</p>

        <RouterLink to="/groups/new" class="btn-outline mt-6 w-full"><Plus class="size-5" /> Tạo nhóm mới</RouterLink>

    </main>
</template>
