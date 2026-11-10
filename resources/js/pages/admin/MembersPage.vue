<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { KeyRound, UserMinus, UserPlus, UserRound } from 'lucide-vue-next';
import AdminShell from '../../layouts/AdminShell.vue';
import Sheet from '../../components/Sheet.vue';
import TempPasswordCard from '../../components/TempPasswordCard.vue';
import UserAvatar from '../../components/UserAvatar.vue';
import { adminApi } from '../../lib/api.js';
import { ignore, useForm } from '../../lib/form.js';
import { toast } from '../../lib/notice.js';
import { formatDate } from '../../lib/time.js';
import { roleIn, session } from '../../lib/session.js';

// Tài khoản và vai trò trong nhóm, xem plan/02 mục 3-4.
const route = useRoute();
const router = useRouter();
const code = computed(() => route.params.group);
const myRole = computed(() => roleIn(code.value));
const members = ref([]);
const selected = ref(null);
const result = ref(null);
const { state, submit } = useForm();

async function load() {
    members.value = await adminApi(code.value, '/members').catch(() => []);
}
onMounted(load);

function canEdit(m) {
    if (m.user_id === session.me?.id || m.role === 'owner') return false;
    return m.role === 'member' || myRole.value === 'owner';
}

// Tài khoản không sửa được (chính mình, trưởng nhóm) thì bấm vào ra trang thành viên.
function open(m) {
    if (!canEdit(m)) return router.push(`/${code.value}/members/${m.id}`);
    selected.value = m;
    result.value = null;
}

async function setRole(role) {
    const res = await submit(() => adminApi(code.value, `/members/${selected.value.id}`, { method: 'PUT', body: { role } })).catch(ignore);
    if (!res) return;
    toast(`Đã đổi ${res.name} thành ${res.role_label}.`);
    selected.value = null;
    load();
}

async function resetPassword() {
    if (!confirm(`Đặt lại mật khẩu cho ${selected.value.name}? Mật khẩu cũ sẽ không dùng được nữa.`)) return;
    result.value = await submit(() => adminApi(code.value, `/members/${selected.value.id}/reset-password`, { method: 'POST' })).catch(ignore);
}

async function revoke() {
    if (!confirm(`Thu hồi tài khoản của ${selected.value.name} khỏi nhóm? Người này vẫn còn trong danh bạ.`)) return;
    const ok = await submit(() => adminApi(code.value, `/members/${selected.value.id}`, { method: 'DELETE' }).then(() => true)).catch(ignore);
    if (!ok) return;
    toast('Đã thu hồi tài khoản.');
    selected.value = null;
    load();
}

function lastLogin(value) {
    return value ? `Đăng nhập ${formatDate(value)}` : 'Chưa đăng nhập';
}
</script>

<template>
    <AdminShell title="Tài khoản & vai trò" :crumbs="[{ label: 'Thêm', to: `/${$route.params.group}/admin/more` }]" mobile-only>
        <p class="text-sm text-muted-foreground">Cấp tài khoản cho người mới ở trang <RouterLink :to="`/${code}/admin/contacts`" class="font-medium text-primary">Danh bạ</RouterLink> (nút "Cấp TK").</p>

        <ul class="card mt-3 divide-y divide-border">
            <li v-for="m in members" :key="m.id" class="flex items-center gap-3 pl-3">
                <RouterLink :to="`/${code}/members/${m.id}`" class="shrink-0" :aria-label="`Trang của ${m.name}`"><UserAvatar :url="m.avatar_url" :name="m.name" class="!size-10" /></RouterLink>
                <button class="flex min-w-0 flex-1 items-center gap-3 py-3 pr-3 text-left" @click="open(m)">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium">{{ m.name }}<span v-if="m.user_id === session.me?.id" class="text-muted-foreground"> (bạn)</span></span>
                        <span class="text-sm text-muted-foreground">{{ m.phone }} · {{ m.must_change_password ? 'Chưa đổi mật khẩu tạm' : lastLogin(m.last_login_at) }}</span>
                    </span>
                    <span class="chip shrink-0" :class="m.role !== 'member' && 'bg-primary/10 text-primary'">{{ m.role_label }}</span>
                </button>
            </li>
        </ul>

        <Sheet :open="selected !== null" :title="selected?.name" @close="selected = null">
            <TempPasswordCard v-if="result" :password="result.temp_password" :message="result.share_message" />
            <div v-else class="grid gap-3">
                <RouterLink v-if="selected" :to="`/${code}/members/${selected.id}`" class="btn-outline"><UserRound class="size-4" /> Xem trang cá nhân</RouterLink>
                <template v-if="myRole === 'owner'">
                    <button v-if="selected?.role === 'member'" class="btn-outline" :disabled="state.busy" @click="setRole('admin')"><UserPlus class="size-4" /> Phong làm Quản trị viên</button>
                    <button v-else class="btn-outline" :disabled="state.busy" @click="setRole('member')">Hạ xuống Thành viên</button>
                </template>
                <button class="btn-outline" :disabled="state.busy" @click="resetPassword"><KeyRound class="size-4" /> Đặt lại mật khẩu</button>
                <button class="btn-outline text-expense" :disabled="state.busy" @click="revoke"><UserMinus class="size-4" /> Thu hồi tài khoản khỏi nhóm</button>
            </div>
        </Sheet>
    </AdminShell>
</template>
