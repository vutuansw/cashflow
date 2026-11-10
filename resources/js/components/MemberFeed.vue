<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import PostCard from './PostCard.vue';
import { api } from '../lib/api.js';
import { monthLabel } from '../lib/time.js';

// Cột "Hoạt động" trang cá nhân: timeline các phiếu một thành viên đóng hoặc nhận trong nhóm, cuộn vô hạn,
// chia mốc tháng. Dùng chung cho trang thành viên và /profile. Xem plan/08 mục 5.
// Slot mặc định: đặt trên đầu cột (vd hàng nút chọn nhóm ở /profile).
const props = defineProps({ code: String, memberId: Number, viewer: Object });
const emit = defineEmits(['login']);

const posts = ref([]);
const page = ref(0);
const hasMore = ref(true);
const loading = ref(false);
const sentinel = ref(null);

const feed = computed(() => {
    const items = [];
    let last = null;
    for (const post of posts.value) {
        const label = monthLabel(post.occurred_at);
        if (label !== last) items.push({ divider: label, key: `m-${label}` });
        last = label;
        items.push({ post, key: `t-${post.id}` });
    }
    return items;
});

// Đổi nhóm/người trong lúc đang tải thì bỏ kết quả cũ.
let token = 0;

function reset() {
    token++;
    loading.value = false;
    posts.value = [];
    page.value = 0;
    hasMore.value = true;
    loadMore();
}

async function loadMore() {
    if (loading.value || !hasMore.value || !props.memberId) return;
    const mine = token;
    loading.value = true;
    try {
        const r = await api(`/g/${props.code}/members/${props.memberId}/timeline?page=${page.value + 1}`);
        if (mine !== token) return;
        posts.value.push(...r.data);
        page.value = r.page;
        hasMore.value = r.has_more;
    } catch {
        if (mine === token) hasMore.value = false;
    } finally {
        if (mine === token) loading.value = false;
    }
}

let observer;
onMounted(() => {
    observer = new IntersectionObserver((entries) => entries[0]?.isIntersecting && loadMore(), { rootMargin: '600px' });
    reset();
});
watch(sentinel, (el, old) => {
    if (old) observer?.unobserve(old);
    if (el) observer?.observe(el);
});
watch(() => [props.code, props.memberId], reset);
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div class="space-y-3">
        <div class="card flex flex-wrap items-center justify-between gap-2 px-4 py-3">
            <h2 class="text-lg font-bold">Hoạt động</h2>
            <slot />
        </div>

        <template v-for="item in feed" :key="item.key">
            <p v-if="item.divider" class="px-1 pt-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ item.divider }}</p>
            <PostCard v-else :post="item.post" :code="code" :viewer="viewer" @login="emit('login')" />
        </template>

        <p v-if="!loading && !posts.length" class="card p-6 text-center text-sm text-muted-foreground">Chưa có khoản đóng góp hay hỗ trợ nào trong nhóm.</p>
        <div ref="sentinel" class="text-center text-sm text-muted-foreground" :class="{ 'py-4': loading || hasMore || posts.length }">
            <template v-if="loading">Đang tải…</template>
            <button v-else-if="hasMore" type="button" class="btn-outline" @click="loadMore">Xem thêm</button>
            <template v-else-if="posts.length">Đã hết.</template>
        </div>
    </div>
</template>
