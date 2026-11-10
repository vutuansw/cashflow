<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { Check, CheckCheck, ChevronRight, ClipboardPaste, KeyRound, Link2, Phone, Plus, Share2, UserPlus, UserRound, X } from 'lucide-vue-next';
import AdminShell from '../../layouts/AdminShell.vue';
import FilterBar from '../../components/FilterBar.vue';
import FormField from '../../components/FormField.vue';
import SegmentedControl from '../../components/SegmentedControl.vue';
import Sheet from '../../components/Sheet.vue';
import TempPasswordCard from '../../components/TempPasswordCard.vue';
import { adminApi } from '../../lib/api.js';
import { copyText } from '../../lib/clipboard.js';
import { pending } from '../../lib/pending.js';
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
// Vào trang luôn mở tab "Đang tham gia"; có người chờ thì khối nổi bật dẫn sang tab "Chờ duyệt".
const status = ref('active');

const editing = ref(null); // null | {} (thêm) | contact (sửa)
const form = reactive({});
const { state, submit } = useForm();

const granting = ref(null);
const grantRole = ref('member');
const granted = ref(null);
const grantForm = useForm();

async function load() {
    if (status.value === 'pending') return; // tab Chờ duyệt dùng danh sách joins
    loading.value = true;
    const params = new URLSearchParams({ status: status.value, search: search.value });
    contacts.value = await adminApi(code.value, `/contacts?${params}`).catch(() => []);
    loading.value = false;
}

let timer;
watch(search, () => { clearTimeout(timer); timer = setTimeout(load, 300); });
watch(status, (value) => (value === 'pending' ? loadJoins() : load()));
onMounted(() => { load(); loadJoins(); });

// Người xin vào nhóm qua link /{ma-nhom}/register và chính link đó, xem plan/13.
const joins = ref([]);
const joinLink = ref(null);
const joinBusy = ref(null);

async function loadJoins() {
    const res = await adminApi(code.value, '/join-requests').catch(() => null);
    if (!res) return;
    joins.value = res.requests;
    joinLink.value = res.link;
    pending.joins = res.requests.length;
}

async function decide(request, action) {
    if (action === 'reject' && !confirm(`Từ chối ${request.name}? Người này sẽ không vào nhóm.`)) return;
    joinBusy.value = request.id;
    try {
        await adminApi(code.value, `/join-requests/${request.id}/${action}`, { method: 'POST' });
        toast(action === 'approve' ? `Đã duyệt ${request.name} vào nhóm.` : `Đã từ chối ${request.name}.`);
        await loadJoins();
        if (action === 'approve') load();
    } catch (e) {
        toast(Object.values(e.errors ?? {})[0]?.[0] ?? e.message, 'error');
        loadJoins();
    } finally {
        joinBusy.value = null;
    }
}

const statusOptions = computed(() => [
    { v: 'active', l: 'Đang tham gia' },
    { v: 'pending', l: 'Chờ duyệt', count: joins.value.length, badge: true },
    { v: 'left', l: 'Đã rời' },
]);

// Ô tìm theo tên dùng chung cho tab Chờ duyệt (lọc tại chỗ, cả SĐT).
const fold = (s) => (s ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/gi, 'd').toLowerCase();
const shownJoins = computed(() => {
    const q = fold(search.value.trim());
    return q ? joins.value.filter((r) => fold(r.name).includes(q) || (r.phone ?? '').includes(q)) : joins.value;
});

const approvingAll = ref(false);
async function approveAll() {
    const list = shownJoins.value;
    if (!list.length || !confirm(`Duyệt ${list.length} người vào nhóm với vai trò Thành viên?`)) return;
    approvingAll.value = true;
    try {
        const res = await adminApi(code.value, '/join-requests/approve', { method: 'POST', body: { ids: list.map((r) => r.id) } });
        if (res.failed.length) {
            toast(`Đã duyệt ${res.approved} người. Chưa duyệt được: ${res.failed.map((f) => `${f.name} (${f.message})`).join('; ')}`, 'error');
        } else {
            toast(`Đã duyệt ${res.approved} người vào nhóm.`);
        }
    } catch (e) {
        toast(Object.values(e.errors ?? {})[0]?.[0] ?? e.message, 'error');
    } finally {
        approvingAll.value = false;
        await loadJoins();
    }
}

async function shareJoinLink() {
    const url = joinLink.value.url;
    if (navigator.share) {
        try {
            await navigator.share({ title: 'Đăng ký vào nhóm trên WePo', url });
            return;
        } catch (e) {
            if (e?.name === 'AbortError') return;
        }
    }
    copyText(url, 'Đã chép link đăng ký.');
}

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
    <AdminShell title="Danh bạ" :crumbs="[{ label: 'Thêm', to: `/${$route.params.group}/admin/more` }]" mobile-only>
        <!-- Khối nổi bật báo có người xin vào nhóm qua link; bấm để sang tab Chờ duyệt (plan/13) -->
        <button v-if="joins.length && status !== 'pending'" type="button" class="card mb-4 flex w-full items-center gap-3 border-amber-400/60 p-4 text-left hover:bg-muted" @click="status = 'pending'">
            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-amber-400/15 text-amber-600"><UserPlus class="size-5" /></span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold">{{ joins.length }} người chờ duyệt vào nhóm</span>
                <span class="block text-sm text-muted-foreground">Đăng ký qua link nhóm. Bấm để xem và duyệt.</span>
            </span>
            <ChevronRight class="size-5 shrink-0 text-muted-foreground" />
        </button>

        <!-- Bộ lọc dùng chung kiểu các màn danh sách khác: ô tìm, dưới là ô chọn trạng thái liền khối -->
        <FilterBar v-model:q="search" placeholder="Tìm theo tên">
            <template #below>
                <SegmentedControl v-model="status" label="Trạng thái" :options="statusOptions" />
            </template>
        </FilterBar>

        <!-- Nút thêm người nằm trong nội dung, không đặt trên thanh trên cùng -->
        <div v-if="status === 'pending'" class="mt-3 flex min-h-10 items-center justify-between gap-2">
            <p class="text-sm text-muted-foreground">{{ shownJoins.length }} người chờ duyệt</p>
            <button v-if="shownJoins.length > 1" type="button" class="btn-soft px-3 text-primary" :disabled="approvingAll" @click="approveAll"><CheckCheck class="size-4" /> {{ approvingAll ? 'Đang duyệt...' : `Duyệt tất cả (${shownJoins.length})` }}</button>
        </div>
        <div v-else class="mt-3 flex items-center justify-between gap-2">
            <p class="text-sm text-muted-foreground">{{ loading ? 'Đang tải...' : `${contacts.length} người` }}</p>
            <div class="flex gap-2">
                <RouterLink :to="`/${code}/admin/contacts/import`" class="btn-soft px-3"><ClipboardPaste class="size-4" /> Nhập nhanh</RouterLink>
                <button type="button" class="btn-soft px-3 text-primary" @click="openEdit()"><Plus class="size-4" /> Thêm người</button>
            </div>
        </div>

        <!-- Link đăng ký vào nhóm: gửi qua Zalo để mọi người tự đăng ký (plan/13) -->
        <div v-if="joinLink" class="card mt-3 flex flex-wrap items-center gap-3 p-3">
            <Link2 class="size-5 shrink-0 text-primary" />
            <div class="min-w-48 flex-1">
                <p class="text-sm font-medium">Link đăng ký vào nhóm</p>
                <p v-if="joinLink.enabled" class="truncate font-mono text-sm text-muted-foreground">{{ joinLink.url }}</p>
                <p v-else class="text-sm text-muted-foreground">Đang tắt. <RouterLink :to="`/${code}/admin/settings#join-link`" class="font-medium text-primary">Bật trong Cài đặt nhóm</RouterLink></p>
            </div>
            <div v-if="joinLink.enabled" class="ml-auto flex shrink-0 gap-2">
                <button type="button" class="btn-soft px-3" @click="copyText(joinLink.url, 'Đã chép link đăng ký.')">Chép</button>
                <button type="button" class="btn-soft px-3" @click="shareJoinLink"><Share2 class="size-4" /> Chia sẻ</button>
            </div>
        </div>

        <!-- Tab Chờ duyệt: duyệt ngay trong danh sách -->
        <template v-if="status === 'pending'">
            <div v-if="!shownJoins.length" class="card mt-3 p-6 text-center">
                <p class="font-medium">{{ search ? 'Không tìm thấy ai' : 'Chưa có ai chờ duyệt' }}</p>
                <p v-if="!search" class="mt-1 text-sm text-muted-foreground">Gửi link đăng ký ở trên qua Zalo, ai đăng ký sẽ hiện ở đây để bạn duyệt.</p>
            </div>
            <ul v-else class="card mt-3 divide-y divide-border">
                <li v-for="r in shownJoins" :key="r.id" class="flex flex-wrap items-center gap-3 p-3">
                    <div class="min-w-48 flex-1">
                        <p class="truncate font-medium">{{ r.name }}</p>
                        <p class="text-sm text-muted-foreground">{{ r.phone }} · xin vào {{ formatDate(r.requested_at) }}</p>
                        <p v-if="r.contact" class="mt-0.5 text-sm text-primary">Trùng SĐT với {{ r.contact.full_name }} trong danh bạ, duyệt sẽ gắn vào người này.</p>
                    </div>
                    <div class="ml-auto flex shrink-0 gap-2">
                        <button type="button" class="btn-outline px-3 text-sm" :disabled="joinBusy === r.id || approvingAll" @click="decide(r, 'reject')"><X class="size-4" /> Từ chối</button>
                        <button type="button" class="btn-primary px-3 text-sm" :disabled="joinBusy === r.id || approvingAll" @click="decide(r, 'approve')"><Check class="size-4" /> Duyệt</button>
                    </div>
                </li>
            </ul>
        </template>

        <div v-else-if="!loading && !contacts.length && !search" class="card mt-3 p-6 text-center">
            <p class="font-medium">Danh bạ còn trống</p>
            <p class="mt-1 text-sm text-muted-foreground">Dán danh sách từ Excel hay Zalo để thêm nhiều người một lúc.</p>
            <RouterLink :to="`/${code}/admin/contacts/import`" class="btn-primary mt-4"><ClipboardPaste class="size-4" /> Nhập nhanh</RouterLink>
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
            <RouterLink v-if="editing?.account" :to="`/${$route.params.group}/members/${editing.account.member_id}`" class="btn-outline mb-4 w-full"><UserRound class="size-4" /> Xem trang cá nhân</RouterLink>
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
