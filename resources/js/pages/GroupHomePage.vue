<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChevronDown, ChevronRight, Clock, Lock, LogOut, MessageSquareText, Settings, Share2, UserPlus, UserRound, Users, Wallet } from 'lucide-vue-next';
import CampaignDayCard from '../components/CampaignDayCard.vue';
import DropdownMenu from '../components/DropdownMenu.vue';
import GroupHeader from '../components/GroupHeader.vue';
import PostCard from '../components/PostCard.vue';
import PostComposer from '../components/PostComposer.vue';
import StatusCard from '../components/StatusCard.vue';
import UserAvatar from '../components/UserAvatar.vue';
import { api, ApiError } from '../lib/api.js';
import { copyText } from '../lib/clipboard.js';
import { formatMoney } from '../lib/money.js';
import { monthLabel } from '../lib/time.js';
import { logout, myPagePath, session } from '../lib/session.js';
import SiteFooter from '../components/SiteFooter.vue';
import GroupRules from '../components/GroupRules.vue';
import TopContributors from '../components/TopContributors.vue';
import InstallHint from '../components/InstallHint.vue';
import { rememberGroup } from '../lib/install.js';

// Trang nhóm /{ma-nhom}, bố cục như nhóm Facebook (plan/08 mục 10d): ảnh bìa, tên, hàng avatar thành viên, nút,
// tab; máy tính chia hai cột (dòng thời gian trái; chiến dịch đang mở phải). Nội dung xem plan/03 mục 4.
const route = useRoute();
const router = useRouter();
const code = computed(() => route.params.group);

const group = ref(null);
const notFound = ref(false);
const summary = ref(null);
const posts = ref([]);
// Bài chờ duyệt của chính mình, trên đầu dòng thời gian, ngoài phân trang (plan/21 mục 6).
const pendingMine = ref([]);
// QTV: số bài thành viên đang chờ duyệt, hiện dòng nhắc trên đầu dòng thời gian.
const pendingReview = ref(0);
const page = ref(0);
const hasMore = ref(true);
const loading = ref(false);
const sentinel = ref(null);

const viewer = computed(() => group.value?.viewer ?? {});

// Chèn mốc tháng giữa các bài.
const feed = computed(() => {
    const items = [];
    let last = null;
    for (const post of posts.value) {
        const label = monthLabel(post.occurred_at);
        if (label !== last) items.push({ divider: label, key: `m-${label}` });
        last = label;
        items.push({ post, key: post.kind === 'campaign_day' ? post.key : `${post.kind === 'status' ? 's' : 't'}-${post.id}` });
    }
    return items;
});

async function loadHeader() {
    notFound.value = false;
    try {
        group.value = await api(`/g/${code.value}`);
        document.title = `${group.value.name} - WePo`;
    } catch (e) {
        if (e instanceof ApiError && [404, 423].includes(e.status)) notFound.value = e.status;
        return;
    }
    if (group.value.can_view) {
        rememberGroup(code.value);
        api(`/g/${code.value}/summary`).then((s) => (summary.value = s)).catch(() => {});
        resetFeed();
    }
}

function resetFeed() {
    posts.value = [];
    pendingMine.value = [];
    page.value = 0;
    hasMore.value = true;
    loadMore();
}

async function loadMore() {
    if (loading.value || !hasMore.value) return;
    loading.value = true;
    try {
        const r = await api(`/g/${code.value}/timeline?page=${page.value + 1}`);
        posts.value.push(...r.data);
        if (r.pending_mine) pendingMine.value = r.pending_mine;
        if (r.pending_review !== undefined) pendingReview.value = r.pending_review;
        page.value = r.page;
        hasMore.value = r.has_more;
    } catch {
        hasMore.value = false;
    } finally {
        loading.value = false;
    }
}

// Đăng xong: chèn thẻ lên đầu ngay, không tải lại (plan/21 mục 7).
function posted(card) {
    if (card.status === 'pending') pendingMine.value.unshift(card);
    else posts.value.unshift(card);
}
function removed(post) {
    posts.value = posts.value.filter((p) => !(p.kind === 'status' && p.id === post.id));
    pendingMine.value = pendingMine.value.filter((p) => p.id !== post.id);
}

const login = () => router.push({ path: '/login', query: { next: route.fullPath } });
// Khách hoặc người chưa là thành viên: nút Tham gia nhóm dẫn tới link nhóm /{ma-nhom}/register (plan/30 mục 1).
const canJoin = computed(() => !viewer.value.is_member && !viewer.value.join_pending && !!viewer.value.join_link_enabled);
// Đăng xuất ngay ở trang nhóm (plan/16), về trang đăng nhập như ở trang chọn nhóm.
async function signOut() {
    await logout();
    router.replace('/login');
}
// Trang của tôi trong nhóm (plan/08 mục 10b); không thuộc nhóm này thì trang của mình ở nhóm khác (plan/24).
const myPage = computed(() => (viewer.value.member_handle ? `/${code.value}/members/${viewer.value.member_handle}` : myPagePath()));
// Tab "Chiến dịch" mở chiến dịch đang mở mới nhất.
const openCampaign = computed(() => summary.value?.campaigns?.[0] ?? null);
// Cột phải (máy tính): chiến dịch đang mở và/hoặc nội quy (plan/15). QTV luôn thấy box nội quy để mời viết.
const showRules = computed(() => !!summary.value && (!!summary.value.rules || !!viewer.value?.can_manage));
const hasAside = computed(() => !!openCampaign.value || showRules.value);
// QTV ẩn / bỏ ẩn phiếu khỏi Top đóng góp (plan/22): tải lại tóm tắt để box xếp lại.
const reloadSummary = () => api(`/g/${code.value}/summary`).then((s) => (summary.value = s)).catch(() => {});

async function share() {
    const url = window.location.href;
    if (navigator.share) {
        try {
            await navigator.share({ title: group.value.name, url });
            return;
        } catch (e) {
            if (e?.name === 'AbortError') return;
        }
    }
    copyText(url, 'Đã chép link trang nhóm.');
}

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
            <GroupHeader :group="group" :code="code">
                <!-- Góc phải ngang tên nhóm: nút tài khoản (như "Đã tham gia ▾" của Facebook) hoặc Đăng nhập -->
                <template #account>
                    <button v-if="!viewer.logged_in" type="button" class="btn-primary !min-h-10 text-sm" @click="login">Đăng nhập</button>
                    <DropdownMenu v-else label="Tài khoản của tôi">
                        <template #trigger="{ open, toggle }">
                            <button type="button" class="btn-soft !min-h-10 !gap-1.5 !rounded-full !py-1 !pl-1 !pr-2.5" :aria-expanded="open" aria-haspopup="menu" :aria-label="viewer.is_member ? 'Thành viên nhóm' : 'Tài khoản'" @click="toggle">
                                <UserAvatar :url="session.me?.avatar_url" :name="session.me?.name" class="!size-8 text-xs" />
                                <span v-if="viewer.is_member" class="hidden sm:inline">Thành viên</span>
                                <ChevronDown class="size-4" />
                            </button>
                        </template>
                        <!-- Một lối duy nhất: trang của mình trong nhóm, sửa ảnh và thông tin ngay ở đó (plan/24). -->
                        <RouterLink :to="myPage" class="menu-item" role="menuitem"><UserRound class="size-5 text-muted-foreground" /> Trang của tôi</RouterLink>
                        <RouterLink to="/groups" class="menu-item" role="menuitem"><Users class="size-5 text-muted-foreground" /> Chọn nhóm khác</RouterLink>
                        <div class="my-1.5 border-t border-border" role="separator"></div>
                        <button type="button" class="menu-item" role="menuitem" @click="signOut"><LogOut class="size-5 text-muted-foreground" /> Đăng xuất</button>
                    </DropdownMenu>
                </template>

                <!-- Bên phải hàng avatar thành viên: điện thoại chỉ hiện biểu tượng. Nút Tham gia nhóm chỉ nằm ở dải mời
                     bên dưới, không lặp lại ở đây (plan/30 mục 1). -->
                <button v-if="group.can_view" type="button" class="btn-soft !min-h-8 max-sm:!size-8 max-sm:!rounded-full max-sm:!p-0 sm:!min-h-9" aria-label="Chia sẻ" title="Chia sẻ" @click="share"><Share2 class="size-4" /> <span class="hidden sm:inline">Chia sẻ</span></button>
                <RouterLink v-if="viewer.can_manage" :to="`/${code}/admin`" class="btn-soft !min-h-8 max-sm:!size-8 max-sm:!rounded-full max-sm:!p-0 sm:!min-h-9" aria-label="Quản trị" title="Quản trị"><Settings class="size-4" /> <span class="hidden sm:inline">Quản trị</span></RouterLink>

                <template v-if="group.can_view" #tabs>
                    <RouterLink :to="`/${code}`" class="border-b-[3px] border-primary px-3 py-3 text-primary">Dòng thời gian</RouterLink>
                    <RouterLink :to="`/${code}/stats`" class="border-b-[3px] border-transparent px-3 py-3 text-muted-foreground hover:text-foreground">Thống kê</RouterLink>
                    <RouterLink v-if="openCampaign" :to="`/${code}/campaigns/${openCampaign.slug}`" class="whitespace-nowrap border-b-[3px] border-transparent px-3 py-3 text-muted-foreground hover:text-foreground">Chiến dịch</RouterLink>
                </template>
            </GroupHeader>

            <!-- Nhóm công khai, chưa là thành viên: mời tham gia qua link nhóm (plan/30 mục 1) -->
            <div v-if="canJoin && group.can_view" class="mx-auto mt-4 max-w-5xl px-4">
                <div class="card flex flex-wrap items-center gap-3 p-3 text-sm">
                    <UserPlus class="size-5 shrink-0 text-primary" />
                    <span class="min-w-0 flex-1">Bạn chưa ở trong nhóm này. Tham gia để đóng quỹ, đăng bài và xem trang của mình.</span>
                    <RouterLink :to="`/${code}/register`" class="btn-primary !min-h-10 px-4 max-sm:w-full">Tham gia nhóm</RouterLink>
                </div>
            </div>

            <!-- Đã xin vào nhóm qua link, đang chờ duyệt (plan/13); nhóm công khai vẫn xem như khách -->
            <div v-if="viewer.join_pending && group.can_view" class="mx-auto mt-4 max-w-5xl px-4">
                <p class="card flex items-center gap-2 p-3 text-sm"><Clock class="size-5 shrink-0 text-primary" /> Bạn đã gửi yêu cầu vào nhóm. Đang chờ quản trị viên duyệt.</p>
            </div>

            <!-- Nhóm riêng tư: chỉ hiện tên và ảnh bìa, mời đăng nhập -->
            <div v-if="!group.can_view" class="mx-auto mt-6 max-w-xl px-4">
                <div class="card p-6 text-center">
                    <component :is="viewer.join_pending ? Clock : Lock" class="mx-auto size-8 text-muted-foreground" />
                    <h2 class="mt-3 font-semibold">Nhóm riêng tư</h2>
                    <p v-if="viewer.join_pending" class="mt-1 text-sm text-muted-foreground">Bạn đã gửi yêu cầu vào nhóm. Đang chờ quản trị viên duyệt.</p>
                    <p v-else class="mt-1 text-sm text-muted-foreground">
                        {{ viewer.logged_in ? 'Tài khoản của bạn không thuộc nhóm này. Nhờ quản trị viên cấp tài khoản cho bạn.' : 'Đăng nhập bằng tài khoản quản trị viên đã cấp để xem thu chi của nhóm.' }}
                    </p>
                    <button v-if="!viewer.logged_in" type="button" class="btn-primary mt-4 w-full" @click="login">Đăng nhập</button>
                    <RouterLink v-if="!viewer.join_pending && viewer.join_link_enabled" :to="`/${code}/register`" class="btn-outline mt-3 w-full">Tham gia nhóm</RouterLink>
                </div>
            </div>

            <!-- Điện thoại: chiến dịch đang mở rồi dòng thời gian. Máy tính: dòng thời gian trái, chiến dịch phải.
                 Số dư và biểu đồ nằm ở tab Thống kê. -->
            <div v-else class="mx-auto mt-4 grid max-w-5xl grid-cols-[minmax(0,1fr)] items-start gap-4 px-4" :class="{ 'lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]': hasAside }">
                <!-- Điện thoại: gợi ý thêm WePo ra màn hình chính để lần sau chạm icon là vào nhóm. -->
                <InstallHint class="!mb-0 lg:hidden" />
                <aside v-if="hasAside" class="space-y-3 lg:sticky lg:top-4 lg:col-start-2 lg:row-start-1">


                    <!-- Chiến dịch đang mở, mỗi thẻ có nút "Đóng quỹ ngay" (phần đầu trang không lặp lại nút này) -->
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
                        <RouterLink :to="`/${code}/campaigns/${c.slug}`" class="btn-primary mt-3 w-full"><Wallet class="size-4" /> Đóng quỹ ngay</RouterLink>
                    </section>
                    <GroupRules v-if="showRules" :rules="summary.rules" :code="code" :can-manage="viewer.can_manage" />
                    <TopContributors v-if="summary?.top_contributors" :top="summary.top_contributors" :code="code" :can-manage="viewer.can_manage" @changed="reloadSummary" />
                    <!-- Máy tính: chân trang dưới cột chiến dịch (dòng thời gian cuộn vô tận nên không đặt ở đáy trang). -->
                    <SiteFooter class="hidden !pt-2 lg:block" />
                </aside>

                <div class="space-y-3 lg:col-start-1 lg:row-start-1">

                    <!-- Ô viết bài và bài chờ duyệt của mình; QTV có dòng nhắc bài thành viên chờ duyệt (plan/21) -->
                    <RouterLink v-if="viewer.can_manage && pendingReview" :to="`/${code}/admin/posts`" class="card flex min-h-12 items-center gap-3 border-amber-400/60 px-4 py-2.5 text-sm">
                        <MessageSquareText class="size-5 shrink-0 text-amber-600" />
                        <span class="flex-1 font-medium">{{ pendingReview }} bài đang chờ duyệt</span>
                        <ChevronRight class="size-5 text-muted-foreground" />
                    </RouterLink>
                    <PostComposer v-if="viewer.can_post" :code="code" :viewer="viewer" @posted="posted" />
                    <StatusCard v-for="p in pendingMine" :key="`pending-${p.id}`" :post="p" :code="code" :viewer="viewer" @removed="removed" />

                    <!-- Timeline -->
                    <template v-for="item in feed" :key="item.key">
                        <p v-if="item.divider" class="px-1 pt-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ item.divider }}</p>
                        <CampaignDayCard v-else-if="item.post.kind === 'campaign_day'" :post="item.post" :code="code" />
                        <StatusCard v-else-if="item.post.kind === 'status'" :post="item.post" :code="code" :viewer="viewer" @login="login" @removed="removed" />
                        <PostCard v-else :post="item.post" :code="code" :viewer="viewer" @login="login" />
                    </template>

                    <p v-if="!loading && !posts.length" class="card p-6 text-center text-sm text-muted-foreground">Chưa có bài đăng hay khoản thu chi nào.</p>
                    <div ref="sentinel" class="py-4 text-center text-sm text-muted-foreground">
                        <template v-if="loading">Đang tải…</template>
                        <button v-else-if="hasMore" type="button" class="btn-outline" @click="loadMore">Xem thêm</button>
                        <template v-else-if="posts.length">Đã hết.</template>
                    </div>
                    <SiteFooter v-if="!hasAside" class="hidden !pt-0 lg:block" />
                </div>
            </div>
        </template>

    </div>
</template>
