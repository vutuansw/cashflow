<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { CheckCircle2, CircleDashed, CircleDot, MinusCircle } from 'lucide-vue-next';
import PostCard from './PostCard.vue';
import { api } from '../lib/api.js';
import { formatMoney } from '../lib/money.js';
import { monthLabel } from '../lib/time.js';

// Hoạt động của một thành viên trong một nhóm: thẻ tóm tắt đóng quỹ và timeline các phiếu người đó đóng
// hoặc nhận, cuộn vô hạn. Dùng chung cho trang thành viên và /ca-nhan. Xem plan/08 mục 5.
const props = defineProps({ code: String, memberId: Number, stats: Object, viewer: Object });
const emit = defineEmits(['login']);

const posts = ref([]);
const page = ref(0);
const hasMore = ref(true);
const loading = ref(false);
const sentinel = ref(null);

const STATUS = {
    paid: { icon: CheckCircle2, label: 'Đã đóng', class: 'text-income' },
    partial: { icon: CircleDot, label: 'Đóng thiếu', class: 'text-amber-700' },
    unpaid: { icon: CircleDashed, label: 'Chưa đóng', class: 'text-muted-foreground' },
    exempt: { icon: MinusCircle, label: 'Được miễn', class: 'text-muted-foreground' },
};

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
        <section v-if="stats" class="card divide-y divide-border">
            <div class="grid grid-cols-2 divide-x divide-border text-center">
                <div class="p-3">
                    <p class="text-xs text-muted-foreground">Đã đóng {{ stats.year }}</p>
                    <p class="text-lg font-semibold tabular-nums text-income">{{ formatMoney(stats.paid_year) }}</p>
                </div>
                <div class="p-3">
                    <p class="text-xs text-muted-foreground">Chiến dịch đã đóng</p>
                    <p class="text-lg font-semibold tabular-nums">{{ stats.campaigns_paid }}/{{ stats.campaigns_total }}</p>
                </div>
            </div>
            <RouterLink v-for="c in stats.open_campaigns" :key="c.slug" :to="`/${code}/chien-dich/${c.slug}`" class="flex items-center justify-between gap-3 px-3 py-2.5 text-sm hover:bg-muted">
                <span class="min-w-0 truncate"><span class="text-muted-foreground">Đang mở:</span> {{ c.name }}</span>
                <span class="flex shrink-0 items-center gap-1" :class="STATUS[c.status]?.class">
                    <component :is="STATUS[c.status]?.icon" class="size-4" />
                    {{ STATUS[c.status]?.label }}<template v-if="c.status === 'partial'"> {{ formatMoney(c.paid).replace(' đ', '') }}/{{ formatMoney(c.expected) }}</template>
                </span>
            </RouterLink>
        </section>

        <template v-for="item in feed" :key="item.key">
            <p v-if="item.divider" class="pt-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ item.divider }}</p>
            <PostCard v-else :post="item.post" :code="code" :viewer="viewer" @login="emit('login')" />
        </template>

        <p v-if="!loading && !posts.length" class="card p-6 text-center text-sm text-muted-foreground">Chưa có hoạt động trong nhóm.</p>
        <div ref="sentinel" class="py-4 text-center text-sm text-muted-foreground">
            <template v-if="loading">Đang tải…</template>
            <button v-else-if="hasMore" type="button" class="btn-outline" @click="loadMore">Xem thêm</button>
            <template v-else-if="posts.length">Đã hết.</template>
        </div>
    </div>
</template>
