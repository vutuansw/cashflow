<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { Camera, Eye, ImageUp, KeyRound, LogOut, Maximize2, MoreHorizontal, Settings, Trash2, UserPen, Users, Plus } from 'lucide-vue-next';
import BackButton from '../components/BackButton.vue';
import DropdownMenu from '../components/DropdownMenu.vue';
import FormField from '../components/FormField.vue';
import MemberFeed from '../components/MemberFeed.vue';
import MemberIntro from '../components/MemberIntro.vue';
import PhotoViewer from '../components/PhotoViewer.vue';
import ProfileHeader from '../components/ProfileHeader.vue';
import Sheet from '../components/Sheet.vue';
import { api, ApiError } from '../lib/api.js';
import { ignore, useForm } from '../lib/form.js';
import { shrinkImage } from '../lib/image.js';
import { toast } from '../lib/notice.js';
import { homePath, logout, session, setMe } from '../lib/session.js';

// Trang cá nhân của tôi /ca-nhan, kiểu Facebook: đổi ảnh bìa, ảnh đại diện, sửa thông tin, xem hoạt động
// của mình trong từng nhóm. Chỉ chính chủ sửa được. Xem plan/08 mục 1-5.
const router = useRouter();
const me = computed(() => session.me);
const groups = computed(() => me.value?.groups ?? []);

// ---- Ảnh bìa, ảnh đại diện ----
const LABEL = { avatar: 'ảnh đại diện', cover: 'ảnh bìa' };
const inputs = { avatar: ref(null), cover: ref(null) };
const avatarInput = inputs.avatar;
const coverInput = inputs.cover;
const imageMenu = ref(null); // 'avatar' | 'cover' khi đang mở menu ảnh
const uploading = ref(null);
const viewing = ref(null);
const menu = ref(false);

/** Chưa có ảnh thì mở ngay chọn ảnh; có rồi thì hiện menu Xem / Đổi / Gỡ. */
function tapImage(kind) {
    if (uploading.value) return;
    if (me.value?.[`${kind}_url`]) imageMenu.value = kind;
    else pick(kind);
}

function pick(kind) {
    imageMenu.value = null;
    inputs[kind].value?.click();
}

function view(kind) {
    viewing.value = [{ lg: me.value[`${kind}_url`] }];
    imageMenu.value = null;
}

async function upload(kind, event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    uploading.value = kind;
    try {
        const body = new FormData();
        body.append('kind', kind);
        body.append('image', await shrinkImage(file, 2000));
        setMe((await api('/me/images', { method: 'POST', body })).data);
        toast(`Đã đổi ${LABEL[kind]}.`);
    } catch (e) {
        toast(e instanceof ApiError ? Object.values(e.errors ?? {})[0]?.[0] ?? e.message : 'Không kết nối được máy chủ, bạn thử lại nhé.', 'error');
    } finally {
        uploading.value = null;
    }
}

async function removeImage(kind) {
    imageMenu.value = null;
    if (!confirm(`Gỡ ${LABEL[kind]}?`)) return;
    try {
        setMe((await api(`/me/images/${kind}`, { method: 'DELETE' })).data);
        toast(`Đã gỡ ${LABEL[kind]}.`);
    } catch {
        toast(`Chưa gỡ được ${LABEL[kind]}, bạn thử lại nhé.`, 'error');
    }
}

// ---- Sửa thông tin ----
const editing = ref(false);
const form = reactive({ name: '', bio: '', email: '' });
const { state, submit } = useForm();

function edit() {
    Object.assign(form, { name: me.value.name, bio: me.value.bio ?? '', email: me.value.email ?? '' });
    state.errors = {};
    editing.value = true;
}

async function save() {
    const res = await submit(() => api('/me', { method: 'PUT', body: form })).catch(ignore);
    if (!res) return;
    setMe(res.data);
    editing.value = false;
    toast('Đã lưu.');
}

// ---- Hoạt động trong nhóm ----
const KEY = 'profile.group';
function remembered() {
    try {
        return localStorage.getItem(KEY);
    } catch {
        return null;
    }
}
const selectedCode = ref(remembered());
const current = computed(() => groups.value.find((g) => g.code === selectedCode.value) ?? groups.value[0] ?? null);
const groupInfo = ref(null);
const profile = ref(null); // { member, stats } của tôi trong nhóm đang chọn
const activityError = ref(null);

// Giới thiệu lấy vai trò, ngày tham gia theo nhóm; lời giới thiệu lấy từ session để vừa lưu là thấy ngay.
const introMember = computed(() => (profile.value ? { ...profile.value.member, bio: me.value?.bio } : { bio: me.value?.bio }));

async function loadActivity() {
    const g = current.value;
    groupInfo.value = null;
    profile.value = null;
    activityError.value = null;
    if (!g) return;
    try {
        const [info, mine] = await Promise.all([api(`/g/${g.code}`), api(`/g/${g.code}/members/${g.member_id}`)]);
        if (current.value?.code !== g.code) return;
        groupInfo.value = info;
        profile.value = mine;
    } catch (e) {
        activityError.value = e instanceof ApiError && e.status === 423 ? 'Nhóm đang tạm khóa.' : 'Chưa tải được hoạt động trong nhóm.';
    }
}

function choose(code) {
    selectedCode.value = code;
    try {
        localStorage.setItem(KEY, code);
    } catch {
        // Trình duyệt chặn lưu trữ: chỉ không nhớ được nhóm đã chọn.
    }
}

watch(() => current.value?.code, loadActivity, { immediate: true });

async function signOut() {
    await logout();
    router.replace('/dang-nhap');
}

document.title = 'Trang cá nhân · WePo';
</script>

<template>
    <div class="min-h-dvh pb-10">
        <header class="sticky top-0 z-10 border-b border-border bg-background/90 backdrop-blur">
            <div class="mx-auto flex min-h-14 max-w-5xl items-center gap-2 px-2">
                <BackButton :to="homePath()" />
                <p class="min-w-0 flex-1 truncate font-semibold">Trang cá nhân</p>
                <button v-if="me" type="button" class="icon-btn" aria-label="Thêm" @click="menu = true"><MoreHorizontal class="size-5" /></button>
            </div>
        </header>

        <template v-if="me">
            <ProfileHeader :member="me" :role="current?.role_label" :context="current?.name" interactive @cover="tapImage('cover')" @avatar="tapImage('avatar')">
                <template #cover>
                    <button type="button" class="absolute right-2.5 top-2.5 grid size-8 place-items-center rounded-full bg-black/45 text-white backdrop-blur hover:bg-black/60" :aria-label="me.cover_url ? 'Đổi ảnh bìa' : 'Thêm ảnh bìa'" :title="me.cover_url ? 'Đổi ảnh bìa' : 'Thêm ảnh bìa'" :disabled="!!uploading" @click.stop="tapImage('cover')">
                        <span v-if="uploading === 'cover'" class="text-xs">…</span>
                        <Camera v-else class="size-4" />
                    </button>
                </template>
                <template #avatar>
                    <button type="button" class="absolute bottom-2 right-2 grid size-9 place-items-center rounded-full bg-muted text-foreground shadow ring-2 ring-card hover:bg-border" :aria-label="me.avatar_url ? 'Đổi ảnh đại diện' : 'Thêm ảnh đại diện'" :disabled="!!uploading" @click.stop="tapImage('avatar')">
                        <span v-if="uploading === 'avatar'" class="text-xs">…</span>
                        <Camera v-else class="size-5" />
                    </button>
                </template>
                <!-- Cài đặt tài khoản: thay đổi thông tin, chọn nhóm, đổi mật khẩu, đăng xuất -->
                <DropdownMenu label="Cài đặt tài khoản">
                    <template #trigger="{ open, toggle }">
                        <button type="button" class="btn-pill" :class="{ 'bg-primary/10': open }" aria-label="Cài đặt" title="Cài đặt" aria-haspopup="menu" :aria-expanded="open" @click="toggle"><Settings class="size-[18px]" /></button>
                    </template>
                    <button type="button" class="menu-item" role="menuitem" @click="edit"><UserPen class="size-5 text-muted-foreground" /> Thay đổi thông tin</button>
                    <RouterLink to="/nhom" class="menu-item" role="menuitem"><Users class="size-5 text-muted-foreground" /> Chọn nhóm</RouterLink>
                    <RouterLink to="/doi-mat-khau" class="menu-item" role="menuitem"><KeyRound class="size-5 text-muted-foreground" /> Đổi mật khẩu</RouterLink>
                    <div class="my-1.5 border-t border-border"></div>
                    <button type="button" class="menu-item text-expense" role="menuitem" @click="signOut"><LogOut class="size-5" /> Đăng xuất</button>
                </DropdownMenu>
            </ProfileHeader>

            <input ref="avatarInput" type="file" accept="image/*" class="sr-only" tabindex="-1" aria-hidden="true" @change="upload('avatar', $event)" />
            <input ref="coverInput" type="file" accept="image/*" class="sr-only" tabindex="-1" aria-hidden="true" @change="upload('cover', $event)" />

            <!-- Điện thoại: Giới thiệu rồi Hoạt động. Máy tính: Giới thiệu cột trái (dính khi cuộn), Hoạt động cột phải.
                 Đổi mật khẩu, chọn nhóm, đăng xuất nằm trong menu bánh răng; trang người khác thấy mở từ menu ⋯. -->
            <div class="mx-auto mt-4 grid max-w-5xl grid-cols-[minmax(0,1fr)] items-start gap-4 px-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                <MemberIntro class="lg:sticky lg:top-18" :member="introMember" :stats="profile?.stats" :group-name="current?.name" :code="current?.code">
                    <template #bio-empty>
                        <button type="button" class="btn-soft mt-3 w-full" @click="edit"><Plus class="size-4" /> Thêm lời giới thiệu</button>
                    </template>
                </MemberIntro>

                <div>
                    <p v-if="!groups.length" class="card p-6 text-center text-sm text-muted-foreground">Bạn chưa thuộc nhóm nào. Tạo nhóm mới hoặc nhờ trưởng nhóm cấp tài khoản.</p>
                    <p v-else-if="activityError" class="card p-6 text-center text-sm text-muted-foreground">{{ activityError }}</p>
                    <MemberFeed v-else-if="current" :code="current.code" :member-id="current.member_id" :viewer="groupInfo?.viewer">
                        <!-- Thuộc nhiều nhóm: chọn nhóm để xem hoạt động của mình ở nhóm đó -->
                        <div v-if="groups.length > 1" class="-mx-1 flex max-w-full gap-1.5 overflow-x-auto px-1" role="tablist" aria-label="Chọn nhóm">
                            <button v-for="g in groups" :key="g.code" type="button" role="tab" :aria-selected="current.code === g.code" class="chip !min-h-8 shrink-0 !px-3 !text-sm" :class="current.code === g.code ? '!bg-primary !text-primary-foreground' : ''" @click="choose(g.code)">{{ g.name }}</button>
                        </div>
                    </MemberFeed>
                </div>
            </div>
        </template>

        <!-- Menu ⋯ -->
        <Sheet :open="menu" title="Trang cá nhân" @close="menu = false">
            <div class="grid gap-3">
                <RouterLink v-if="current" :to="`/${current.code}/thanh-vien/${current.member_id}`" class="btn-outline"><Eye class="size-4" /> Xem như người khác</RouterLink>
                <button type="button" class="btn-outline" @click="menu = false; pick('avatar')"><Camera class="size-4" /> Đổi ảnh đại diện</button>
                <button type="button" class="btn-outline" @click="menu = false; pick('cover')"><ImageUp class="size-4" /> Đổi ảnh bìa</button>
            </div>
        </Sheet>

        <!-- Menu ảnh: Xem / Đổi / Gỡ -->
        <Sheet :open="imageMenu !== null" :title="imageMenu === 'cover' ? 'Ảnh bìa' : 'Ảnh đại diện'" @close="imageMenu = null">
            <div class="grid gap-3">
                <button type="button" class="btn-outline" @click="view(imageMenu)"><Maximize2 class="size-4" /> Xem ảnh</button>
                <button type="button" class="btn-outline" @click="pick(imageMenu)"><ImageUp class="size-4" /> Chọn ảnh khác</button>
                <button type="button" class="btn-outline text-expense" @click="removeImage(imageMenu)"><Trash2 class="size-4" /> Gỡ ảnh</button>
            </div>
            <p class="mt-3 text-sm text-muted-foreground">{{ imageMenu === 'cover' ? 'Ảnh bìa được cắt theo tỉ lệ 3:1 ở giữa ảnh.' : 'Ảnh đại diện được cắt vuông ở giữa ảnh.' }}</p>
        </Sheet>

        <!-- Sửa thông tin -->
        <Sheet :open="editing" title="Sửa thông tin" @close="editing = false">
            <form class="space-y-4" @submit.prevent="save">
                <FormField v-model="form.name" label="Họ tên" :error="state.errors.name" required maxlength="120" />
                <FormField label="Giới thiệu" :error="state.errors.bio" :hint="`${form.bio.length}/160 · Hiện ở mục Giới thiệu trên trang của bạn.`">
                    <textarea v-model="form.bio" class="field min-h-20" :class="{ 'field-error': state.errors.bio }" maxlength="160" rows="3" placeholder="Vd: Thủ quỹ chi đoàn A12"></textarea>
                </FormField>
                <FormField v-model="form.email" label="Email" type="email" hint="Để lấy lại mật khẩu khi quên. Không hiện cho người khác." :error="state.errors.email" />
                <FormField label="Số điện thoại" hint="Muốn đổi số, nhờ trưởng nhóm liên hệ quản trị WePo. Không hiện cho người khác.">
                    <input class="field" :value="me?.phone" disabled />
                </FormField>
                <button class="btn-primary w-full" :disabled="state.busy">Lưu</button>
            </form>
        </Sheet>

        <PhotoViewer v-if="viewing" :photos="viewing" @close="viewing = null" />
    </div>
</template>
