<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { Settings } from 'lucide-vue-next';
import BrandMark from '../components/BrandMark.vue';
import { canManage, session } from '../lib/session.js';

// Trang xem nhóm. Timeline, thống kê, tim và bình luận làm ở mốc M2 (plan/03 mục 4).
const route = useRoute();
const code = computed(() => route.params.group);
const group = computed(() => session.me?.groups.find((g) => g.code === code.value));
</script>

<template>
    <main class="mx-auto min-h-dvh max-w-xl px-4 py-6">
        <div class="flex items-center justify-between">
            <BrandMark />
            <RouterLink v-if="canManage(code)" :to="`/${code}/quan-tri`" class="btn-outline"><Settings class="size-4" /> Quản trị</RouterLink>
            <RouterLink v-else-if="!session.me" :to="`/dang-nhap?next=/${code}`" class="btn-outline">Đăng nhập</RouterLink>
        </div>
        <div class="card mt-8 p-6 text-center">
            <h1 class="text-xl font-semibold">{{ group?.name ?? code }}</h1>
            <p class="mt-2 text-muted-foreground">Dòng thời gian thu chi của nhóm sẽ hiện ở đây.</p>
        </div>
    </main>
</template>
