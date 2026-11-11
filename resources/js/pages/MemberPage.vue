<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Camera, House, KeyRound, Link2, Lock, LogOut, MoreHorizontal, Plus, Settings, UserPen, Users } from 'lucide-vue-next';
import BackButton from '../components/BackButton.vue';
import DropdownMenu from '../components/DropdownMenu.vue';
import MemberFeed from '../components/MemberFeed.vue';
import MemberIntro from '../components/MemberIntro.vue';
import PhotoViewer from '../components/PhotoViewer.vue';
import ProfileEditSheets from '../components/ProfileEditSheets.vue';
import ProfileHeader from '../components/ProfileHeader.vue';
import Sheet from '../components/Sheet.vue';
import { api, ApiError } from '../lib/api.js';
import { copyText } from '../lib/clipboard.js';
import { useProfileEditor } from '../lib/profileEditor.js';
import { logout, session } from '../lib/session.js';
import SiteFooter from '../components/SiteFooter.vue';

// Trang thành viên /{ma-nhom}/members/{mã ngẫu nhiên} (plan/25), bố cục như trang cá nhân Facebook: phần đầu (ảnh bìa, avatar,
// tên), cột trái "Giới thiệu", cột phải "Hoạt động" (phiếu người đó đóng hoặc nhận). Xem plan/08 mục 1, 2, 5.
// Trang của chính mình thì sửa luôn tại đây: ảnh bìa, ảnh đại diện, thông tin, lời giới thiệu (plan/24).
const route = useRoute();
const router = useRouter();
const code = computed(() => route.params.group);
const handle = computed(() => route.params.handle);

const group = ref(null);
const profile = ref(null);
const error = ref(null);
const viewing = ref(null);
const menu = ref(false);

// Chủ trang: tên, ảnh, lời giới thiệu lấy từ tài khoản đang đăng nhập để sửa xong là thấy ngay.
const member = computed(() => {
    const m = profile.value?.member;
    if (!m?.is_me || !session.me) return m;
    const { name, avatar_url, cover_url, bio } = session.me;
    return { ...m, name, avatar_url, cover_url, bio };
});
const isMe = computed(() => !!member.value?.is_me);

async function load() {
    error.value = null;
    profile.value = null;
    try {
        const [g, p] = await Promise.all([api(`/g/${code.value}`), api(`/g/${code.value}/members/${handle.value}`)]);
        group.value = g;
        profile.value = p;
        document.title = `${p.member.name} · ${g.name}`;
    } catch (e) {
        error.value = e instanceof ApiError ? e.status : 500;
    }
}

const login = () => router.push({ path: '/login', query: { next: route.fullPath } });
const open = (url) => url && (viewing.value = [{ lg: url }]);
const editor = useProfileEditor(open);

// Bấm ảnh: chủ trang thì đổi ảnh (chưa có thì chọn luôn), người khác thì phóng to.
const tapImage = (kind) => (isMe.value ? editor.tapImage(kind) : open(member.value?.[`${kind}_url`]));

async function signOut() {
    await logout();
    router.replace('/login');
}

function copyLink() {
    menu.value = false;
    copyText(window.location.href, 'Đã chép link trang này.');
}

onMounted(load);
watch([code, handle], load);
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
            <ProfileHeader :member="member" :role="member.role_label" :context="group?.name" :context-to="`/${code}`" :interactive="isMe" @cover="tapImage('cover')" @avatar="tapImage('avatar')">
                <template v-if="isMe" #cover>
                    <button type="button" class="absolute right-2.5 top-2.5 grid size-8 place-items-center rounded-full bg-black/45 text-white backdrop-blur hover:bg-black/60" :aria-label="member.cover_url ? 'Đổi ảnh bìa' : 'Thêm ảnh bìa'" :title="member.cover_url ? 'Đổi ảnh bìa' : 'Thêm ảnh bìa'" :disabled="!!editor.uploading.value" @click.stop="editor.tapImage('cover')">
                        <span v-if="editor.uploading.value === 'cover'" class="text-xs">…</span>
                        <Camera v-else class="size-4" />
                    </button>
                </template>
                <template v-if="isMe" #avatar>
                    <button type="button" class="absolute bottom-1 right-1 grid size-8 place-items-center rounded-full bg-muted text-foreground shadow ring-2 ring-card hover:bg-border" :aria-label="member.avatar_url ? 'Đổi ảnh đại diện' : 'Thêm ảnh đại diện'" :disabled="!!editor.uploading.value" @click.stop="editor.tapImage('avatar')">
                        <span v-if="editor.uploading.value === 'avatar'" class="text-xs">…</span>
                        <Camera v-else class="size-4" />
                    </button>
                </template>
                <template #badge><span v-if="member.removed" class="chip !bg-amber-100 !text-amber-800">Đã rời nhóm</span></template>
                <!-- Cài đặt tài khoản: thay đổi thông tin, chọn nhóm, đổi mật khẩu, đăng xuất -->
                <DropdownMenu v-if="isMe" label="Cài đặt tài khoản">
                    <template #trigger="{ open: shown, toggle }">
                        <button type="button" class="btn-pill" :class="{ 'bg-primary/10': shown }" aria-label="Cài đặt" title="Cài đặt" aria-haspopup="menu" :aria-expanded="shown" @click="toggle"><Settings class="size-4" /></button>
                    </template>
                    <button type="button" class="menu-item" role="menuitem" @click="editor.edit"><UserPen class="size-5 text-muted-foreground" /> Thay đổi thông tin</button>
                    <RouterLink to="/groups" class="menu-item" role="menuitem"><Users class="size-5 text-muted-foreground" /> Chọn nhóm</RouterLink>
                    <RouterLink to="/change-password" class="menu-item" role="menuitem"><KeyRound class="size-5 text-muted-foreground" /> Đổi mật khẩu</RouterLink>
                    <div class="my-1.5 border-t border-border"></div>
                    <button type="button" class="menu-item text-expense" role="menuitem" @click="signOut"><LogOut class="size-5" /> Đăng xuất</button>
                </DropdownMenu>
            </ProfileHeader>
            <ProfileEditSheets v-if="isMe" :editor="editor" />

            <!-- Điện thoại: Giới thiệu rồi Hoạt động. Máy tính: Giới thiệu cột trái (dính khi cuộn), Hoạt động cột phải. -->
            <div class="mx-auto mt-4 grid max-w-5xl grid-cols-[minmax(0,1fr)] items-start gap-4 px-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                <!-- Máy tính: chân trang dưới thẻ Giới thiệu (cột trái dính khi cuộn), vì dòng hoạt động cuộn vô tận. -->
                <div class="lg:sticky lg:top-18">
                    <MemberIntro :member="member" :stats="profile.stats" :group-name="group?.name" :code="code">
                        <template v-if="isMe" #bio-empty>
                            <button type="button" class="btn-soft mt-3 w-full" @click="editor.edit"><Plus class="size-4" /> Thêm lời giới thiệu</button>
                        </template>
                    </MemberIntro>
                    <SiteFooter class="hidden !pt-3 lg:block" />
                </div>
                <MemberFeed :code="code" :member-handle="member.handle" :viewer="group?.viewer" :composer="member.is_me && !!group?.viewer?.can_post" @login="login" />
            </div>
        </template>

        <!-- Menu ⋯ -->
        <Sheet :open="menu" :title="member?.name" @close="menu = false">
            <div class="grid gap-3">
                <button type="button" class="btn-outline" @click="copyLink"><Link2 class="size-4" /> Chép link trang này</button>
                <RouterLink :to="`/${code}`" class="btn-outline"><House class="size-4" /> Về trang nhóm</RouterLink>
            </div>
        </Sheet>

        <PhotoViewer v-if="viewing" :photos="viewing" @close="viewing = null" />
    </div>
</template>
