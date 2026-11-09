<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminShell from '../../layouts/AdminShell.vue';
import FormField from '../../components/FormField.vue';
import MoneyInput from '../../components/MoneyInput.vue';
import Sheet from '../../components/Sheet.vue';
import { adminApi } from '../../lib/api.js';
import { BANKS } from '../../lib/banks.js';
import { copyText } from '../../lib/clipboard.js';
import { ignore, useForm } from '../../lib/form.js';
import { toast } from '../../lib/notice.js';
import { loadMe, session } from '../../lib/session.js';
import { shrinkImage } from '../../lib/image.js';
import { ImagePlus, Trash2 } from 'lucide-vue-next';

// Cài đặt nhóm, xem plan/06 mục 3. SePay làm ở M4.
const route = useRoute();
const router = useRouter();
const code = computed(() => route.params.group);
const link = computed(() => `${location.origin}/${code.value}`);

const form = reactive({});
const loaded = ref(false);
const myRole = ref(null);
const emails = ref('');
const { state, submit } = useForm();

const members = ref([]);
const transferTo = ref(null);
const transferOpen = ref(false);
const deleteOpen = ref(false);
const confirmCode = ref('');
const danger = useForm();

function fill(data) {
    Object.assign(form, data);
    emails.value = (data.notify_emails ?? []).join(', ');
    myRole.value = data.my_role;
}

onMounted(async () => {
    fill(await adminApi(code.value, '/settings'));
    loaded.value = true;
});

async function save() {
    const body = { ...form, notify_emails: emails.value.split(/[,\s]+/).filter(Boolean) };
    const res = await submit(() => adminApi(code.value, '/settings', { method: 'PUT', body })).catch(ignore);
    if (!res) return;
    fill(res);
    await loadMe(true);
    toast('Đã lưu cài đặt.');
}

// Ảnh bìa (tỉ lệ 3:1) và ảnh đại diện (vuông), hiện trên trang nhóm và khi dán link vào Zalo/Facebook.
const uploading = ref(null);
async function uploadImage(kind, event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    uploading.value = kind;
    try {
        const body = new FormData();
        body.append('kind', kind);
        body.append('image', await shrinkImage(file, 2000));
        const res = await adminApi(code.value, '/settings/images', { method: 'POST', body });
        form.avatar_url = res.avatar_url;
        form.cover_url = res.cover_url;
        toast(kind === 'cover' ? 'Đã đổi ảnh bìa.' : 'Đã đổi ảnh đại diện.');
    } catch (e) {
        toast(Object.values(e.errors ?? {})[0]?.[0] ?? e.message, 'error');
    } finally {
        uploading.value = null;
    }
}
async function removeImage(kind) {
    const res = await adminApi(code.value, `/settings/images/${kind}`, { method: 'DELETE' });
    form.avatar_url = res.avatar_url;
    form.cover_url = res.cover_url;
}

async function openTransfer() {
    members.value = (await adminApi(code.value, '/members')).filter((m) => m.user_id !== session.me?.id);
    transferTo.value = null;
    transferOpen.value = true;
}

async function transfer() {
    const m = members.value.find((x) => x.user_id === transferTo.value);
    if (!m || !confirm(`Chuyển quyền trưởng nhóm cho ${m.name}? Bạn sẽ thành Quản trị viên.`)) return;
    const res = await danger.submit(() => adminApi(code.value, '/transfer-owner', { method: 'POST', body: { user_id: m.user_id } })).catch(ignore);
    if (!res) return;
    fill(res);
    await loadMe(true);
    transferOpen.value = false;
    toast(`${m.name} là trưởng nhóm mới.`);
}

async function destroy() {
    const ok = await danger.submit(() => adminApi(code.value, '', { method: 'DELETE', body: { confirm_code: confirmCode.value } }).then(() => true)).catch(ignore);
    if (!ok) return;
    await loadMe(true);
    toast('Đã xóa nhóm.');
    router.replace('/nhom');
}
</script>

<template>
    <AdminShell title="Cài đặt nhóm">
        <form v-if="loaded" class="space-y-6" @submit.prevent="save">
            <section class="card overflow-hidden">
                <div class="relative h-28 bg-gradient-to-br from-primary/30 to-primary/5 sm:h-36" :style="form.cover_url ? { backgroundImage: `url(${form.cover_url})`, backgroundSize: 'cover', backgroundPosition: 'center' } : {}">
                    <div class="absolute right-2 top-2 flex gap-1">
                        <label class="btn-outline !min-h-9 cursor-pointer !px-3 text-sm"><ImagePlus class="size-4" /> {{ uploading === 'cover' ? 'Đang tải…' : 'Ảnh bìa' }}<input type="file" accept="image/*" class="sr-only" :disabled="uploading" @change="uploadImage('cover', $event)" /></label>
                        <button v-if="form.cover_url" type="button" class="btn-outline !min-h-9 !px-2" aria-label="Bỏ ảnh bìa" @click="removeImage('cover')"><Trash2 class="size-4" /></button>
                    </div>
                </div>
                <div class="-mt-8 flex items-end gap-3 px-4 pb-4">
                    <label class="relative block size-16 shrink-0 cursor-pointer overflow-hidden rounded-2xl border-4 border-card bg-primary text-primary-foreground" aria-label="Đổi ảnh đại diện">
                        <img v-if="form.avatar_url" :src="form.avatar_url" alt="" class="size-full object-cover" />
                        <span v-else class="grid size-full place-items-center text-2xl font-bold">{{ form.name?.charAt(0) }}</span>
                        <span class="absolute inset-x-0 bottom-0 bg-black/50 py-0.5 text-center text-[10px] text-white">{{ uploading === 'avatar' ? '…' : 'Đổi' }}</span>
                        <input type="file" accept="image/*" class="sr-only" :disabled="uploading" @change="uploadImage('avatar', $event)" />
                    </label>
                    <p class="pb-1 text-sm text-muted-foreground">Ảnh bìa và ảnh đại diện hiện trên trang nhóm, kể cả nhóm riêng tư, và khi dán link vào Zalo, Facebook.</p>
                </div>
            </section>

            <section class="card space-y-4 p-4">
                <h2 class="font-semibold">Thông tin nhóm</h2>
                <FormField label="Link nhóm" hint="Mã nhóm chỉ quản trị Quỹ nhóm mới đổi được, vì đổi sẽ làm hỏng link đã chia sẻ.">
                    <div class="flex gap-2">
                        <input class="field" :value="link" readonly />
                        <button type="button" class="btn-outline shrink-0" @click="copyText(link, 'Đã chép link nhóm.')">Chép</button>
                    </div>
                </FormField>
                <FormField v-model="form.name" label="Tên nhóm" :error="state.errors.name" required />
                <FormField label="Mô tả" :error="state.errors.description">
                    <textarea v-model="form.description" rows="3" class="field"></textarea>
                </FormField>
                <FormField label="Ai xem được dòng thời gian?" :error="state.errors.visibility">
                    <select v-model="form.visibility" class="field">
                        <option value="public">Công khai: ai có link đều xem được</option>
                        <option value="private">Riêng tư: chỉ người có tài khoản trong nhóm</option>
                    </select>
                </FormField>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Số dư đầu kỳ" :error="state.errors.opening_balance"><MoneyInput v-model="form.opening_balance" /></FormField>
                    <FormField v-model="form.opening_balance_date" label="Tính từ ngày" type="date" :error="state.errors.opening_balance_date" />
                </div>
            </section>

            <section class="card space-y-4 p-4">
                <div>
                    <h2 class="font-semibold">Tài khoản nhận quỹ</h2>
                    <p class="text-sm text-muted-foreground">Dùng để tạo mã QR đóng quỹ. Số tài khoản được mã hóa khi lưu.</p>
                </div>
                <FormField label="Ngân hàng" :error="state.errors.bank_code">
                    <select v-model="form.bank_code" class="field">
                        <option :value="null">Chọn ngân hàng</option>
                        <option v-for="b in BANKS" :key="b.code" :value="b.code">{{ b.name }}</option>
                    </select>
                </FormField>
                <FormField v-model="form.bank_account_no" label="Số tài khoản" inputmode="numeric" :error="state.errors.bank_account_no" />
                <FormField v-model="form.bank_account_name" label="Tên chủ tài khoản" placeholder="NGUYEN VAN AN" :error="state.errors.bank_account_name" />
            </section>

            <section class="card space-y-4 p-4">
                <h2 class="font-semibold">Thông báo email</h2>
                <FormField v-model="emails" label="Email nhận thông báo" hint="Cách nhau bằng dấu phẩy, tối đa 5. Để trống thì gửi trưởng nhóm." :error="state.errors.notify_emails || state.errors['notify_emails.0']" />
            </section>

            <button class="btn-primary sticky bottom-20 w-full shadow-lg lg:bottom-4" :disabled="state.busy">Lưu cài đặt</button>
        </form>

        <section v-if="loaded && myRole === 'owner'" class="card mt-8 space-y-3 border-expense/40 p-4">
            <h2 class="font-semibold text-expense">Vùng nguy hiểm</h2>
            <button class="btn-outline w-full" @click="openTransfer">Chuyển trưởng nhóm cho người khác</button>
            <button class="btn-outline w-full text-expense" @click="deleteOpen = true">Xóa nhóm</button>
        </section>

        <Sheet :open="transferOpen" title="Chuyển trưởng nhóm" @close="transferOpen = false">
            <div class="space-y-4">
                <p v-if="!members.length" class="text-sm text-muted-foreground">Chưa có ai khác có tài khoản trong nhóm. Cấp tài khoản ở trang Danh bạ trước.</p>
                <FormField v-else label="Người nhận" :error="danger.state.errors.user_id">
                    <select v-model="transferTo" class="field">
                        <option :value="null">Chọn người</option>
                        <option v-for="m in members" :key="m.user_id" :value="m.user_id">{{ m.name }} ({{ m.role_label }})</option>
                    </select>
                </FormField>
                <button class="btn-primary w-full" :disabled="!transferTo || danger.state.busy" @click="transfer">Chuyển quyền</button>
            </div>
        </Sheet>

        <Sheet :open="deleteOpen" title="Xóa nhóm" @close="deleteOpen = false">
            <div class="space-y-4">
                <p class="text-sm">Nhóm ngừng hoạt động ngay, link nhóm không mở được nữa. Dữ liệu được giữ 30 ngày rồi xóa hẳn. Gõ <b class="font-mono">{{ code }}</b> để xác nhận.</p>
                <FormField v-model="confirmCode" label="Mã nhóm" autocapitalize="none" :error="danger.state.errors.confirm_code" />
                <button class="btn-danger w-full" :disabled="confirmCode !== code || danger.state.busy" @click="destroy">Xóa nhóm này</button>
            </div>
        </Sheet>
    </AdminShell>
</template>
