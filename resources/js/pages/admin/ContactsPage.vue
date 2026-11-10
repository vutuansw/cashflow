<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { ClipboardPaste, KeyRound, Phone, Plus, Search, UserPlus, UserRound } from 'lucide-vue-next';
import AdminShell from '../../layouts/AdminShell.vue';
import FormField from '../../components/FormField.vue';
import Sheet from '../../components/Sheet.vue';
import TempPasswordCard from '../../components/TempPasswordCard.vue';
import { adminApi } from '../../lib/api.js';
import { ignore, useForm } from '../../lib/form.js';
import { toast } from '../../lib/notice.js';
import { formatDate } from '../../lib/time.js';
import { roleIn } from '../../lib/session.js';

// Danh bạ thành viên, xem plan/02 mục 4.
const route = useRoute();
const code = computed(() => route.params.group);
const isOwner = computed(() => roleIn(code.value) === 'owner');

const contacts = ref([]);
const loading = ref(true);
const search = ref('');
const status = ref('active');

const editing = ref(null); // null | {} (thêm) | contact (sửa)
const form = reactive({});
const { state, submit } = useForm();

const granting = ref(null);
const grantRole = ref('member');
const granted = ref(null);
const grantForm = useForm();

async function load() {
    loading.value = true;
    const params = new URLSearchParams({ status: status.value, search: search.value });
    contacts.value = await adminApi(code.value, `/contacts?${params}`).catch(() => []);
    loading.value = false;
}

let timer;
watch(search, () => { clearTimeout(timer); timer = setTimeout(load, 300); });
watch(status, load);
onMounted(load);

function openEdit(contact = null) {
    editing.value = contact ?? {};
    Object.assign(form, {
        full_name: contact?.full_name ?? '',
        phone: contact?.phone ?? '',
        birthday: contact?.birthday ?? '',
        address: contact?.address ?? '',
        note: contact?.note ?? '',
        status: contact?.status ?? 'active',
    });
}

async function save() {
    const isNew = !editing.value.id;
    const body = { ...form, birthday: form.birthday || null };
    const res = await submit(() => adminApi(code.value, isNew ? '/contacts' : `/contacts/${editing.value.id}`, { method: isNew ? 'POST' : 'PUT', body })).catch(ignore);
    if (!res) return;
    toast(isNew ? 'Đã thêm vào danh bạ.' : 'Đã lưu.');
    editing.value = null;
    load();
}

async function remove() {
    if (!confirm(`Xóa ${editing.value.full_name} khỏi danh bạ?`)) return;
    const res = await submit(() => adminApi(code.value, `/contacts/${editing.value.id}`, { method: 'DELETE' }).then(() => true)).catch(ignore);
    if (!res) {
        if (state.errors.contact) toast(state.errors.contact, 'error');
        return;
    }
    toast('Đã xóa.');
    editing.value = null;
    load();
}

function openGrant(contact) {
    granting.value = contact;
    grantRole.value = 'member';
    granted.value = null;
}

async function grant() {
    const res = await grantForm.submit(() => adminApi(code.value, '/members', { method: 'POST', body: { contact_id: granting.value.id, role: grantRole.value } })).catch(ignore);
    if (!res) return;
    granted.value = res;
    load();
}

function birthday(value) {
    return formatDate(value);
}
</script>

<template>
    <AdminShell title="Danh bạ" :crumbs="[{ label: 'Thêm', to: `/${$route.params.group}/quan-tri/them` }]" mobile-only>
        <template #actions>
            <RouterLink :to="`/${code}/quan-tri/danh-ba/nhap`" class="icon-btn" aria-label="Nhập nhanh"><ClipboardPaste class="size-5" /></RouterLink>
            <button class="icon-btn" aria-label="Thêm người" @click="openEdit()"><Plus class="size-5" /></button>
        </template>

        <div class="flex gap-2">
            <label class="relative flex-1">
                <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <input v-model="search" type="search" placeholder="Tìm theo tên" class="field pl-9" />
            </label>
            <select v-model="status" class="field w-auto">
                <option value="active">Đang tham gia</option>
                <option value="left">Đã rời nhóm</option>
                <option value="all">Tất cả</option>
            </select>
        </div>

        <p class="mt-3 text-sm text-muted-foreground">{{ loading ? 'Đang tải...' : `${contacts.length} người` }}</p>

        <div v-if="!loading && !contacts.length && !search" class="card mt-3 p-6 text-center">
            <p class="font-medium">Danh bạ còn trống</p>
            <p class="mt-1 text-sm text-muted-foreground">Dán danh sách từ Excel hay Zalo để thêm nhiều người một lúc.</p>
            <RouterLink :to="`/${code}/quan-tri/danh-ba/nhap`" class="btn-primary mt-4"><ClipboardPaste class="size-4" /> Nhập nhanh</RouterLink>
        </div>

        <ul v-else class="card mt-3 divide-y divide-border">
            <li v-for="c in contacts" :key="c.id" class="flex items-center gap-3 p-3">
                <button class="min-w-0 flex-1 text-left" @click="openEdit(c)">
                    <span class="block truncate font-medium">{{ c.full_name }}</span>
                    <span class="flex flex-wrap items-center gap-x-2 text-sm text-muted-foreground">
                        <span v-if="c.phone">{{ c.phone }}</span>
                        <span v-if="c.birthday">{{ birthday(c.birthday) }}</span>
                        <span v-if="c.status === 'left'" class="chip">Đã rời</span>
                    </span>
                </button>
                <span v-if="c.account" class="chip shrink-0" :class="c.account.role !== 'member' && 'bg-primary/10 text-primary'"><KeyRound class="size-3" /> {{ c.account.role_label }}</span>
                <button v-else-if="c.phone && c.status === 'active'" class="btn-outline shrink-0 px-3 text-sm" @click="openGrant(c)"><UserPlus class="size-4" /> Cấp TK</button>
                <a v-if="c.phone" :href="`tel:${c.phone}`" class="icon-btn shrink-0" :aria-label="`Gọi ${c.full_name}`"><Phone class="size-4" /></a>
            </li>
        </ul>

        <Sheet :open="editing !== null" :title="editing?.id ? 'Sửa thông tin' : 'Thêm người'" @close="editing = null">
            <RouterLink v-if="editing?.account" :to="`/${$route.params.group}/thanh-vien/${editing.account.member_id}`" class="btn-outline mb-4 w-full"><UserRound class="size-4" /> Xem trang cá nhân</RouterLink>
            <form class="space-y-4" @submit.prevent="save">
                <FormField v-model="form.full_name" label="Họ tên" :error="state.errors.full_name" required />
                <FormField v-model="form.phone" label="Số điện thoại" type="tel" inputmode="tel" :error="state.errors.phone" />
                <FormField v-model="form.birthday" label="Ngày sinh" type="date" :error="state.errors.birthday" />
                <FormField v-model="form.address" label="Địa chỉ" :error="state.errors.address" />
                <FormField v-model="form.note" label="Ghi chú" :error="state.errors.note" />
                <label v-if="editing?.id" class="flex min-h-11 items-center gap-3">
                    <input v-model="form.status" type="checkbox" true-value="left" false-value="active" class="size-5 accent-primary" />
                    Đã rời nhóm (giữ lịch sử, không phải đóng chiến dịch mới)
                </label>
                <button class="btn-primary w-full" :disabled="state.busy">Lưu</button>
                <button v-if="editing?.id && !editing.account" type="button" class="btn-outline w-full text-expense" @click="remove">Xóa khỏi danh bạ</button>
            </form>
        </Sheet>

        <Sheet :open="granting !== null" :title="`Cấp tài khoản cho ${granting?.full_name ?? ''}`" @close="granting = null">
            <TempPasswordCard v-if="granted" :password="granted.temp_password" :message="granted.share_message" />
            <form v-else class="space-y-4" @submit.prevent="grant">
                <p class="text-sm text-muted-foreground">Đăng nhập bằng số {{ granting?.phone }}. Mật khẩu tạm sẽ hiện ra để bạn gửi cho họ.</p>
                <FormField label="Vai trò" :error="grantForm.state.errors.role || grantForm.state.errors.contact_id">
                    <select v-model="grantRole" class="field">
                        <option value="member">Thành viên: xem timeline, thả tim, bình luận</option>
                        <option v-if="isOwner" value="admin">Quản trị viên: thu chi, danh bạ, báo cáo</option>
                    </select>
                </FormField>
                <button class="btn-primary w-full" :disabled="grantForm.state.busy">Cấp tài khoản</button>
            </form>
        </Sheet>
    </AdminShell>
</template>
