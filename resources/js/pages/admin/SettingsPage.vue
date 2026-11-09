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

// Cài đặt nhóm, xem plan/06 mục 3. Ảnh đại diện/ảnh bìa và SePay làm ở M2/M4.
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
