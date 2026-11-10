<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { House, Link2, Lock, MoreHorizontal, Pencil } from 'lucide-vue-next';
import BackButton from '../components/BackButton.vue';
import MemberFeed from '../components/MemberFeed.vue';
import MemberIntro from '../components/MemberIntro.vue';
import PhotoViewer from '../components/PhotoViewer.vue';
import ProfileHeader from '../components/ProfileHeader.vue';
import Sheet from '../components/Sheet.vue';
import { api, ApiError } from '../lib/api.js';
import { copyText } from '../lib/clipboard.js';
import SiteFooter from '../components/SiteFooter.vue';

// Trang thành viên /{ma-nhom}/members/{id}, bố cục như trang cá nhân Facebook: phần đầu (ảnh bìa, avatar,
// tên), cột trái "Giới thiệu", cột phải "Hoạt động" (phiếu người đó đóng hoặc nhận). Xem plan/08 mục 1, 2, 5.
const route = useRoute();
const router = useRouter();
const code = computed(() => route.params.group);
const id = computed(() => Number(route.params.id));

const group = ref(null);
const profile = ref(null);
const error = ref(null);
const viewing = ref(null);
const menu = ref(false);

const member = computed(() => profile.value?.member);

async function load() {
    error.value = null;
    profile.value = null;
    try {
        const [g, p] = await Promise.all([api(`/g/${code.value}`), api(`/g/${code.value}/members/${id.value}`)]);
        group.value = g;
        profile.value = p;
        document.title = `${p.member.name} · ${g.name}`;
    } catch (e) {
        error.value = e instanceof ApiError ? e.status : 500;
    }
}

const login = () => router.push({ path: '/login', query: { next: route.fullPath } });
const open = (url) => url && (viewing.value = [{ lg: url }]);

function copyLink() {
    menu.value = false;
    copyText(window.location.href, 'Đã chép link trang này.');
}

onMounted(load);
watch([code, id], load);
</script>

<template>
    <div class="min-h-dvh pb-10">
        <header class="sticky top-0 z-10 border-b border-border bg-background/90 backdrop-blur">
            <div class="mx-auto flex min-h-14 max-w-5xl items-center gap-2 px-2">
                <BackButton :to="`/${code}`" label="trang nhóm" />
                <p class="min-w-0 flex-1 truncate font-semibold">{{ group?.name ?? code }}</p>
                <button v-if="member" type="button" class="icon-btn" aria-label="Thêm" @click="menu = true"><MoreHorizontal class="size-5" /></button>
            </div>
        </header>

        <div v-if="error === 403" class="card mx-4 mt-4 p-6 text-center sm:mx-auto sm:max-w-xl">
            <Lock class="mx-auto size-8 text-muted-foreground" />
            <p class="mt-3 font-semibold">Nhóm riêng tư</p>
            <p class="mt-1 text-sm text-muted-foreground">Đăng nhập bằng tài khoản của nhóm để xem trang thành viên.</p>
            <button type="button" class="btn-primary mt-4 w-full" @click="login">Đăng nhập</button>
        </div>
        <div v-else-if="error" class="card mx-4 mt-4 p-6 text-center sm:mx-auto sm:max-w-xl">
            <p class="font-semibold">Không tìm thấy thành viên</p>
            <p class="mt-1 text-sm text-muted-foreground">Người này có thể đã rời nhóm.</p>
            <RouterLink :to="`/${code}`" class="btn-outline mt-4">Về trang nhóm</RouterLink>
        </div>

        <template v-else-if="member">
            <ProfileHeader :member="member" :role="member.role_label" :context="group?.name" :context-to="`/${code}`" @cover="open(member.cover_url)" @avatar="open(member.avatar_url)">
                <template #badge><span v-if="member.removed" class="chip !bg-amber-100 !text-amber-800">Đã rời nhóm</span></template>
                <RouterLink v-if="member.is_me" to="/profile" class="btn-pill" aria-label="Chỉnh sửa trang cá nhân" title="Chỉnh sửa trang cá nhân"><Pencil class="size-[18px]" /></RouterLink>
            </ProfileHeader>

            <!-- Điện thoại: Giới thiệu rồi Hoạt động. Máy tính: Giới thiệu cột trái (dính khi cuộn), Hoạt động cột phải. -->
            <div class="mx-auto mt-4 grid max-w-5xl grid-cols-[minmax(0,1fr)] items-start gap-4 px-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                <!-- Máy tính: chân trang dưới thẻ Giới thiệu (cột trái dính khi cuộn), vì dòng hoạt động cuộn vô tận. -->
                <div class="lg:sticky lg:top-18">
                    <MemberIntro :member="member" :stats="profile.stats" :group-name="group?.name" :code="code" />
                    <SiteFooter class="hidden !pt-3 lg:block" />
                </div>
                <MemberFeed :code="code" :member-id="member.id" :viewer="group?.viewer" @login="login" />
            </div>
        </template>

        <!-- Menu ⋯ -->
        <Sheet :open="menu" :title="member?.name" @close="menu = false">
            <div class="grid gap-3">
                <button type="button" class="btn-outline" @click="copyLink"><Link2 class="size-4" /> Chép link trang này</button>
                <RouterLink v-if="member?.is_me" to="/profile" class="btn-outline"><Pencil class="size-4" /> Chỉnh sửa trang cá nhân</RouterLink>
                <RouterLink :to="`/${code}`" class="btn-outline"><House class="size-4" /> Về trang nhóm</RouterLink>
            </div>
        </Sheet>

        <PhotoViewer v-if="viewing" :photos="viewing" @close="viewing = null" />
    </div>
</template>
