<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChevronRight, Globe, Lock, Settings, UserRound } from 'lucide-vue-next';
import CampaignDayCard from '../components/CampaignDayCard.vue';
import MonthBars from '../components/MonthBars.vue';
import PostCard from '../components/PostCard.vue';
import { api, ApiError } from '../lib/api.js';
import { formatMoney } from '../lib/money.js';
import { monthLabel } from '../lib/time.js';

// Trang nhóm /{ma-nhom}: phần đầu, số dư + biểu đồ 6 tháng, chiến dịch đang mở, bộ lọc, timeline. Xem plan/03 mục 4 và wireframe M2.
const route = useRoute();
const router = useRouter();
const code = computed(() => route.params.group);

const group = ref(null);
const notFound = ref(false);
const summary = ref(null);
const posts = ref([]);
const page = ref(0);
const hasMore = ref(true);
const loading = ref(false);
const filter = ref({ type: null, category_id: null });
const sentinel = ref(null);

const viewer = computed(() => group.value?.viewer ?? {});
const lastSixMonths = computed(() => {
    if (!summary.value) return [];
    const now = new Date();
    const upTo = summary.value.year === now.getFullYear() ? now.getMonth() + 1 : 12;
    return summary.value.months.slice(Math.max(0, upTo - 6), upTo);
});
const categories = computed(() => [...(summary.value?.expense_by_category ?? []), ...(summary.value?.income_by_category ?? [])]);

// Chèn mốc tháng giữa các bài.
const feed = computed(() => {
    const items = [];
    let last = null;
    for (const post of posts.value) {
        const label = monthLabel(post.occurred_at);
        if (label !== last) items.push({ divider: label, key: `m-${label}` });
        last = label;
        items.push({ post, key: post.kind === 'campaign_day' ? post.key : `t-${post.id}` });
    }
    return items;
});

async function loadHeader() {
    notFound.value = false;
    try {
        group.value = await api(`/g/${code.value}`);
        document.title = `${group.value.name} · Quỹ nhóm`;
    } catch (e) {
        if (e instanceof ApiError && [404, 423].includes(e.status)) notFound.value = e.status;
        return;
    }
    if (group.value.can_view) {
        api(`/g/${code.value}/summary`).then((s) => (summary.value = s)).catch(() => {});
        resetFeed();
    }
}

function resetFeed() {
    posts.value = [];
    page.value = 0;
    hasMore.value = true;
    loadMore();
}

async function loadMore() {
    if (loading.value || !hasMore.value) return;
    loading.value = true;
    try {
        const params = new URLSearchParams({ page: page.value + 1 });
        if (filter.value.type) params.set('type', filter.value.type);
        if (filter.value.category_id) params.set('category_id', filter.value.category_id);
        const r = await api(`/g/${code.value}/timeline?${params}`);
        posts.value.push(...r.data);
        page.value = r.page;
        hasMore.value = r.has_more;
    } catch {
        hasMore.value = false;
    } finally {
        loading.value = false;
    }
}

function setFilter(next) {
    filter.value = { ...filter.value, ...next };
    resetFeed();
}

const login = () => router.push({ path: '/dang-nhap', query: { next: route.fullPath } });

let observer;
onMounted(() => {
    loadHeader();
    observer = new IntersectionObserver((entries) => entries[0]?.isIntersecting && loadMore(), { rootMargin: '600px' });
});
watch(sentinel, (el, old) => {
    if (old) observer?.unobserve(old);
    if (el) observer?.observe(el);
});
watch(code, () => {
    summary.value = null;
    loadHeader();
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div class="min-h-dvh pb-10">
        <div v-if="notFound" class="mx-auto max-w-xl px-4 py-20 text-center">
            <h1 class="text-xl font-semibold">{{ notFound === 423 ? 'Nhóm đang tạm khóa' : 'Không tìm thấy nhóm' }}</h1>
            <p class="mt-2 text-muted-foreground">{{ notFound === 423 ? 'Nhóm này đang tạm dừng hoạt động.' : 'Link có thể bị gõ sai hoặc nhóm đã bị xóa.' }}</p>
            <RouterLink to="/" class="btn-outline mt-6">Về trang chủ</RouterLink>
        </div>

        <template v-else-if="group">
            <!-- Phần đầu: ảnh bìa, ảnh đại diện, tên nhóm -->
            <header class="mx-auto max-w-xl">
                <div class="h-32 bg-gradient-to-br from-primary/30 to-primary/5 sm:h-44 sm:rounded-b-3xl" :style="group.cover_url ? { backgroundImage: `url(${group.cover_url})`, backgroundSize: 'cover', backgroundPosition: 'center' } : {}"></div>
                <!-- Chỉ ảnh đại diện trồi lên ảnh bìa; tên nhóm và nút nằm hẳn bên dưới để không đè lên ảnh -->
                <div class="flex items-start gap-3 px-4">
                    <img v-if="group.avatar_url" :src="group.avatar_url" alt="" class="relative -mt-8 size-16 shrink-0 rounded-2xl border-4 border-background object-cover" />
                    <span v-else class="relative -mt-8 grid size-16 shrink-0 place-items-center rounded-2xl border-4 border-background bg-primary text-2xl font-bold text-primary-foreground">{{ group.name.charAt(0) }}</span>
                    <div class="min-w-0 flex-1 pt-2">
                        <h1 class="truncate text-lg font-semibold leading-tight">{{ group.name }}</h1>
                        <p class="flex items-center gap-1 text-xs text-muted-foreground">
                            <component :is="group.visibility === 'public' ? Globe : Lock" class="size-3" />
                            {{ group.visibility === 'public' ? 'Công khai' : 'Riêng tư' }}<template v-if="group.members_count"> · {{ group.members_count }} thành viên</template>
                        </p>
                    </div>
                    <RouterLink v-if="viewer.can_manage" :to="`/${code}/quan-tri`" class="btn-outline mt-2 !min-h-10 !px-3 text-sm"><Settings class="size-4" /> Quản trị</RouterLink>
                    <button v-else-if="!viewer.logged_in" type="button" class="btn-outline mt-2 !min-h-10 !px-3 text-sm" @click="login">Đăng nhập</button>
                    <RouterLink v-else to="/ca-nhan" class="icon-btn mt-2" aria-label="Tài khoản"><UserRound class="size-5" /></RouterLink>
                </div>
            </header>

            <!-- Nhóm riêng tư: chỉ hiện tên và ảnh, mời đăng nhập -->
            <div v-if="!group.can_view" class="mx-auto mt-6 max-w-xl px-4">
                <div class="card p-6 text-center">
                    <Lock class="mx-auto size-8 text-muted-foreground" />
                    <h2 class="mt-3 font-semibold">Nhóm riêng tư</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ viewer.logged_in ? 'Tài khoản của bạn không thuộc nhóm này. Nhờ quản trị viên cấp tài khoản cho bạn.' : 'Đăng nhập bằng tài khoản quản trị viên đã cấp để xem thu chi của nhóm.' }}
                    </p>
                    <button v-if="!viewer.logged_in" type="button" class="btn-primary mt-4 w-full" @click="login">Đăng nhập</button>
                </div>
            </div>

            <div v-else class="mx-auto mt-4 max-w-xl space-y-3 px-4">
                <p v-if="group.description" class="text-sm text-muted-foreground">{{ group.description }}</p>

                <!-- Số dư và biểu đồ 6 tháng -->
                <section class="card p-4">
                    <p class="text-sm text-muted-foreground">Số dư quỹ</p>
                    <p class="text-3xl font-semibold tabular-nums">{{ summary ? formatMoney(summary.balance) : '…' }}</p>
                    <div v-if="summary" class="mt-1 flex flex-wrap gap-x-4 text-sm tabular-nums">
                        <span class="text-income">Thu {{ summary.year }} +{{ formatMoney(summary.income).replace(' đ', '') }}</span>
                        <span class="text-expense">Chi −{{ formatMoney(summary.expense).replace(' đ', '') }}</span>
                    </div>
                    <MonthBars v-if="summary" :months="lastSixMonths" :label-year="summary.year" class="mt-3" />
                    <RouterLink :to="`/${code}/thong-ke`" class="mt-2 inline-flex min-h-11 items-center text-sm font-medium text-primary">Xem thống kê chi tiết <ChevronRight class="size-4" /></RouterLink>
                </section>

                <!-- Chiến dịch đang mở -->
                <section v-for="c in summary?.campaigns ?? []" :key="c.id" class="card p-4">
                    <p class="text-xs text-muted-foreground">Chiến dịch đang mở</p>
                    <p class="font-semibold">{{ c.name }}</p>
                    <div v-if="c.members_total" class="mt-2 h-2 rounded-full bg-muted" role="progressbar" :aria-valuenow="c.members_paid" :aria-valuemax="c.members_total">
                        <div class="h-2 rounded-full bg-primary" :style="{ width: `${Math.min(100, (c.members_paid / c.members_total) * 100)}%` }"></div>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <template v-if="c.members_total">{{ c.members_paid }}/{{ c.members_total }} người · </template>
                        Đã thu {{ formatMoney(c.collected) }}<template v-if="c.members_total && c.amount_per_member"> / {{ formatMoney(c.members_total * c.amount_per_member) }}</template>
                    </p>
                    <RouterLink :to="`/${code}/chien-dich/${c.slug}`" class="btn-primary mt-3 w-full">Đóng quỹ ngay</RouterLink>
                </section>

                <!-- Bộ lọc -->
                <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 text-sm">
                    <button v-for="opt in [{ v: null, l: 'Tất cả' }, { v: 'income', l: 'Thu' }, { v: 'expense', l: 'Chi' }]" :key="String(opt.v)" type="button" class="chip !min-h-9 shrink-0 !px-3.5 !text-sm" :class="filter.type === opt.v && !filter.category_id ? '!bg-primary !text-primary-foreground' : ''" @click="setFilter({ type: opt.v, category_id: null })">{{ opt.l }}</button>
                    <select v-if="categories.length" class="chip !min-h-9 shrink-0 !pl-3 !pr-8 !text-sm" :value="filter.category_id ?? ''" aria-label="Lọc theo loại" @change="setFilter({ category_id: $event.target.value || null, type: null })">
                        <option value="">Loại…</option>
                        <option v-for="cat in categories" :key="cat.category_id" :value="cat.category_id">{{ cat.name }}</option>
                    </select>
                </div>

                <!-- Timeline -->
                <template v-for="item in feed" :key="item.key">
                    <p v-if="item.divider" class="pt-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ item.divider }}</p>
                    <CampaignDayCard v-else-if="item.post.kind === 'campaign_day'" :post="item.post" :code="code" />
                    <PostCard v-else :post="item.post" :code="code" :viewer="viewer" @login="login" />
                </template>

                <p v-if="!loading && !posts.length" class="card p-6 text-center text-sm text-muted-foreground">Chưa có khoản thu chi nào{{ filter.type || filter.category_id ? ' khớp bộ lọc' : '' }}.</p>
                <div ref="sentinel" class="py-4 text-center text-sm text-muted-foreground">
                    <template v-if="loading">Đang tải…</template>
                    <button v-else-if="hasMore" type="button" class="btn-outline" @click="loadMore">Xem thêm</button>
                    <template v-else-if="posts.length">Đã hết.</template>
                </div>
            </div>
        </template>

    </div>
</template>
