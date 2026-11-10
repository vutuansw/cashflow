<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Lock, Pencil } from 'lucide-vue-next';
import BackButton from '../components/BackButton.vue';
import MemberActivity from '../components/MemberActivity.vue';
import PhotoViewer from '../components/PhotoViewer.vue';
import ProfileHeader from '../components/ProfileHeader.vue';
import { api, ApiError } from '../lib/api.js';

// Trang thành viên /{ma-nhom}/thanh-vien/{id}: ảnh bìa, ảnh đại diện, tên, vai trò, tóm tắt đóng quỹ
// và timeline các phiếu người đó đóng hoặc nhận trong nhóm. Xem plan/08 mục 1, 2, 5.
const route = useRoute();
const router = useRouter();
const code = computed(() => route.params.group);
const id = computed(() => Number(route.params.id));

const group = ref(null);
const profile = ref(null);
const error = ref(null);
const viewing = ref(null);

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

const login = () => router.push({ path: '/dang-nhap', query: { next: route.fullPath } });
const open = (url) => url && (viewing.value = [{ lg: url }]);

onMounted(load);
watch([code, id], load);
</script>

<template>
    <div class="mx-auto min-h-dvh max-w-xl pb-10">
        <header class="sticky top-0 z-10 flex min-h-14 items-center gap-2 border-b border-border bg-background/90 px-2 backdrop-blur">
            <BackButton :to="`/${code}`" label="trang nhóm" />
            <p class="min-w-0 flex-1 truncate font-semibold">{{ member?.name ?? group?.name ?? code }}</p>
        </header>

        <div v-if="error === 403" class="card m-4 p-6 text-center">
            <Lock class="mx-auto size-8 text-muted-foreground" />
            <p class="mt-3 font-semibold">Nhóm riêng tư</p>
            <p class="mt-1 text-sm text-muted-foreground">Đăng nhập bằng tài khoản của nhóm để xem trang thành viên.</p>
            <button type="button" class="btn-primary mt-4 w-full" @click="login">Đăng nhập</button>
        </div>
        <div v-else-if="error" class="card m-4 p-6 text-center">
            <p class="font-semibold">Không tìm thấy thành viên</p>
            <p class="mt-1 text-sm text-muted-foreground">Người này có thể đã rời nhóm.</p>
            <RouterLink :to="`/${code}`" class="btn-outline mt-4">Về trang nhóm</RouterLink>
        </div>

        <template v-else-if="member">
            <ProfileHeader :member="member" :subtitle="`${member.role_label} · ${group?.name}`" @cover="open(member.cover_url)" @avatar="open(member.avatar_url)">
                <template #badge><span v-if="member.removed" class="chip ml-1 !bg-amber-100 !text-amber-800">Đã rời nhóm</span></template>
                <RouterLink v-if="member.is_me" to="/ca-nhan" class="btn-outline mt-3 !min-h-10 text-sm"><Pencil class="size-4" /> Sửa trang cá nhân</RouterLink>
            </ProfileHeader>

            <MemberActivity class="mt-4 px-4" :code="code" :member-id="member.id" :stats="profile.stats" :viewer="group?.viewer" @login="login" />
        </template>

        <PhotoViewer v-if="viewing" :photos="viewing" @close="viewing = null" />
    </div>
</template>
