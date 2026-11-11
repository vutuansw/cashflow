<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminShell from '../../layouts/AdminShell.vue';
import CoverArt from '../../components/CoverArt.vue';
import SwitchToggle from '../../components/SwitchToggle.vue';
import FormField from '../../components/FormField.vue';
import MoneyInput from '../../components/MoneyInput.vue';
import Sheet from '../../components/Sheet.vue';
import { adminApi } from '../../lib/api.js';
import { BANKS } from '../../lib/banks.js';
import { copyText } from '../../lib/clipboard.js';
import { ignore, useForm } from '../../lib/form.js';
import { toast } from '../../lib/notice.js';
import { formatDateTime } from '../../lib/time.js';
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
    // Bấm "Viết nội quy" / "Sửa" ở trang nhóm: cuộn tới ô nội quy (plan/15).
    if (route.hash === '#rules') {
        await nextTick();
        const el = document.getElementById('rules');
        el?.scrollIntoView({ block: 'center' });
        el?.focus({ preventScroll: true });
    }
});

async function save() {
    const body = { ...form, notify_emails: emails.value.split(/[,\s]+/).filter(Boolean) };
    const res = await submit(() => adminApi(code.value, '/settings', { method: 'PUT', body })).catch(ignore);
    if (!res) return;
    fill(res);
    await loadMe(true);
    toast('Đã lưu cài đặt.');
}

// Ảnh bìa (tỉ lệ 3:1) đầu trang nhóm, cũng là ảnh xem trước khi dán link vào Zalo/Facebook.
// Nhóm không có ảnh đại diện (plan/08 mục 10d).
const uploading = ref(false);
async function uploadCover(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    uploading.value = true;
    try {
        const body = new FormData();
        body.append('image', await shrinkImage(file, 2000));
        const res = await adminApi(code.value, '/settings/images', { method: 'POST', body });
        form.cover_url = res.cover_url;
        toast('Đã đổi ảnh bìa.');
    } catch (e) {
        toast(Object.values(e.errors ?? {})[0]?.[0] ?? e.message, 'error');
    } finally {
        uploading.value = false;
    }
}
async function removeCover() {
    const res = await adminApi(code.value, '/settings/images/cover', { method: 'DELETE' });
    form.cover_url = res.cover_url;
}

const sepayBusy = ref(false);
async function toggleSepay(enabled) {
    if (!enabled && !confirm('Tắt SePay? API key hiện tại sẽ hết hiệu lực, webhook gửi tới sẽ bị từ chối.')) return;
    sepayBusy.value = true;
    try {
        const res = await adminApi(code.value, '/settings/sepay', { method: 'POST', body: { enabled } });
        form.sepay = res.sepay;
    } finally {
        sepayBusy.value = false;
    }
}
async function rotateSepay() {
    if (!confirm('Tạo API key mới? Bạn phải dán key mới vào cấu hình webhook bên SePay, key cũ hết hiệu lực ngay.')) return;
    const res = await adminApi(code.value, '/settings/sepay', { method: 'POST', body: { enabled: true, rotate: true } });
    form.sepay = res.sepay;
    toast('Đã tạo API key mới.');
}
// Link đăng ký vào nhóm, xem plan/13.
const joinBusy = ref(false);
async function toggleJoinLink(enabled) {
    joinBusy.value = true;
    try {
        const res = await adminApi(code.value, '/settings/join-link', { method: 'PUT', body: { enabled } });
        form.join_link = res.join_link;
        toast(enabled ? 'Đã bật link đăng ký.' : 'Đã tắt link đăng ký.');
    } finally {
        joinBusy.value = false;
    }
}

async function toggleNotify(value) {
    const res = await adminApi(code.value, '/settings/notifications', { method: 'PUT', body: { sepay: value } });
    form.notify_settings = res.notify_settings;
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
    router.replace('/groups');
}
</script>

<template>
    <AdminShell title="Cài đặt nhóm" :crumbs="[{ label: 'Thêm', to: `/${$route.params.group}/admin/more` }]" mobile-only>
        <form v-if="loaded" class="space-y-6" @submit.prevent="save">
            <section class="card overflow-hidden">
                <!-- Cùng tỉ lệ 3:1 và nền mặc định như đầu trang nhóm, để thấy đúng ảnh sẽ hiện thế nào -->
                <div class="relative aspect-[3/1] bg-cover bg-center" :style="form.cover_url ? { backgroundImage: `url(${form.cover_url})` } : {}">
                    <CoverArt v-if="!form.cover_url" />
                    <div class="absolute right-2 top-2 flex gap-1">
                        <label class="btn-outline !min-h-9 cursor-pointer !px-3 text-sm"><ImagePlus class="size-4" /> {{ uploading ? 'Đang tải…' : form.cover_url ? 'Đổi ảnh bìa' : 'Thêm ảnh bìa' }}<input type="file" accept="image/*" class="sr-only" :disabled="uploading" @change="uploadCover" /></label>
                        <button v-if="form.cover_url" type="button" class="btn-outline !min-h-9 !px-2" aria-label="Bỏ ảnh bìa" @click="removeCover"><Trash2 class="size-4" /></button>
                    </div>
                </div>
                <p class="p-4 text-sm text-muted-foreground">Ảnh bìa hiện ở đầu trang nhóm (kể cả nhóm riêng tư).</p>
            </section>

            <section class="card space-y-4 p-4">
                <h2 class="font-semibold">Thông tin nhóm</h2>
                <FormField label="Link nhóm" hint="Mã nhóm chỉ quản trị WePo mới đổi được, vì đổi sẽ làm hỏng link đã chia sẻ.">
                    <div class="flex gap-2">
                        <input class="field" :value="link" readonly />
                        <button type="button" class="btn-outline shrink-0" @click="copyText(link, 'Đã chép link nhóm.')">Chép</button>
                    </div>
                </FormField>
                <FormField v-model="form.name" label="Tên nhóm" :error="state.errors.name" required />
                <FormField label="Mô tả" :error="state.errors.description">
                    <textarea v-model="form.description" rows="3" class="field"></textarea>
                </FormField>
                <!-- Hiện ở box "Nội quy" trên trang nhóm, xem plan/15. -->
                <FormField label="Nội quy nhóm" :error="state.errors.rules" hint="Hiện ở trang nhóm, cột bên phải. Để trống thì không hiện.">
                    <textarea id="rules" v-model="form.rules" rows="6" maxlength="3000" class="field" placeholder="Ví dụ: Mỗi người đóng 500.000 đ/năm trước 31/3. Hiếu hỉ nhóm hỗ trợ 500.000 đ. Chi trên 1 triệu cần trưởng nhóm đồng ý."></textarea>
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

            <!-- Link đăng ký vào nhóm, xem plan/13 -->
            <section id="join-link" class="card space-y-3 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">Cho đăng ký qua link</h2>
                        <p class="text-sm text-muted-foreground">Ai có link tự tạo tài khoản và xin vào nhóm. Quản trị viên duyệt trong Danh bạ.</p>
                    </div>
                    <SwitchToggle :model-value="form.join_link?.enabled" label="Cho đăng ký qua link" :disabled="joinBusy" @update:model-value="toggleJoinLink" />
                </div>
                <div v-if="form.join_link?.enabled" class="flex gap-2">
                    <input class="field font-mono text-sm" :value="form.join_link.url" readonly aria-label="Link đăng ký vào nhóm" />
                    <button type="button" class="btn-outline shrink-0" @click="copyText(form.join_link.url, 'Đã chép link đăng ký.')">Chép</button>
                </div>
            </section>

            <!-- SePay: tự nhận biến động số dư, xem plan/05 mục 2 -->
            <section id="sepay" class="card space-y-3 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">Tự nhận biến động số dư qua SePay</h2>
                        <p class="text-sm text-muted-foreground">Tiền chuyển vào tài khoản nhóm tự hiện ở mục Chờ duyệt, nhận ra người đóng theo mã chiến dịch.</p>
                    </div>
                    <button type="button" role="switch" :aria-checked="form.sepay?.enabled" class="relative mt-1 h-7 w-12 shrink-0 rounded-full transition" :class="form.sepay?.enabled ? 'bg-primary' : 'bg-muted'" :disabled="sepayBusy" @click="toggleSepay(!form.sepay?.enabled)">
                        <span class="absolute top-0.5 size-6 rounded-full bg-white shadow transition-all" :class="form.sepay?.enabled ? 'left-[1.375rem]' : 'left-0.5'"></span>
                    </button>
                </div>
                <template v-if="form.sepay?.enabled">
                    <FormField label="Webhook URL">
                        <div class="flex gap-2"><input class="field font-mono text-sm" :value="form.sepay.webhook_url" readonly /><button type="button" class="btn-outline shrink-0" @click="copyText(form.sepay.webhook_url, 'Đã chép Webhook URL.')">Chép</button></div>
                    </FormField>
                    <FormField label="API key">
                        <div class="flex gap-2"><input class="field font-mono text-sm" :value="form.sepay.api_key" readonly /><button type="button" class="btn-outline shrink-0" @click="copyText(form.sepay.api_key, 'Đã chép API key.')">Chép</button></div>
                    </FormField>
                    <button type="button" class="text-sm font-medium text-primary" :disabled="sepayBusy" @click="rotateSepay">Tạo API key mới</button>
                    <p class="rounded-xl bg-muted p-3 text-sm">
                        <template v-if="form.sepay.last_hook_at">Đã kết nối · lần cuối nhận {{ formatDateTime(form.sepay.last_hook_at) }}</template>
                        <template v-else>Chưa nhận webhook nào. Làm theo hướng dẫn dưới rồi dùng chức năng giả lập giao dịch của SePay để thử.</template>
                    </p>
                    <details class="rounded-xl border border-border p-3 text-sm">
                        <summary class="cursor-pointer font-medium">Hướng dẫn cài trên my.sepay.vn</summary>
                        <ol class="mt-2 list-decimal space-y-1 pl-5 text-muted-foreground">
                            <li>Đăng ký tài khoản tại my.sepay.vn và liên kết tài khoản ngân hàng nhận quỹ (đúng số tài khoản ở trên).</li>
                            <li>Vào <b>Tích hợp WebHooks</b> → <b>Thêm webhooks</b>.</li>
                            <li>Chọn tài khoản ngân hàng; sự kiện chọn cả <b>Có tiền vào</b> và <b>Có tiền ra</b>.</li>
                            <li>Gọi đến URL: dán <b>Webhook URL</b> ở trên.</li>
                            <li>Kiểu chứng thực: <b>API Key</b>, dán <b>API key</b> ở trên.</li>
                            <li>"Bỏ qua nếu nội dung giao dịch không có code thanh toán": chọn <b>Không</b>.</li>
                            <li>Lưu, rồi dùng <b>Giả lập giao dịch</b> của SePay với nội dung là mã chiến dịch + tên để thử.</li>
                        </ol>
                    </details>
                </template>
            </section>

            <section class="card space-y-4 p-4">
                <h2 class="font-semibold">Thông báo email</h2>
                <FormField v-model="emails" label="Email nhận thông báo" hint="Cách nhau bằng dấu phẩy, tối đa 5. Để trống thì gửi trưởng nhóm." :error="state.errors.notify_emails || state.errors['notify_emails.0']" />
                <label class="flex min-h-11 items-center gap-3">
                    <input type="checkbox" class="size-5 accent-[var(--primary)]" :checked="form.notify_settings?.sepay" @change="toggleNotify($event.target.checked)" />
                    <span class="text-sm">Gửi email khi có giao dịch SePay mới chờ duyệt (gộp tối đa 15 phút một lần)</span>
                </label>
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
