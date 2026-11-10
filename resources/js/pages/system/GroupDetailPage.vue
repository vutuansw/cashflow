<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ExternalLink } from 'lucide-vue-next';
import SystemShell from '../../layouts/SystemShell.vue';
import FormField from '../../components/FormField.vue';
import Sheet from '../../components/Sheet.vue';
import { systemApi } from '../../lib/systemApi.js';
import { useForm, ignore } from '../../lib/form.js';
import { formatMoney } from '../../lib/money.js';
import { formatDateTime } from '../../lib/time.js';
import { toast } from '../../lib/notice.js';

// Chi tiết nhóm ở /system: số liệu, duyệt/từ chối, khóa/mở khóa, sửa (mã, chế độ, hạn mức AI), xóa/khôi phục. Xem plan/02 mục 6.
const route = useRoute();
const router = useRouter();
const id = computed(() => Number(route.params.id));
const g = ref(null);
const edit = reactive({});
const { state, submit } = useForm();
const lockOpen = ref(false);
const lockReason = ref('');
const deleteOpen = ref(false);
const confirmCode = ref('');

function fill(data) {
    g.value = data;
    Object.assign(edit, { name: data.name, code: data.code, visibility: data.visibility, ai_monthly_quota: data.ai_monthly_quota, note: data.note });
}
onMounted(async () => fill(await systemApi(`/groups/${id.value}`)));

async function act(path, body, message) {
    const res = await submit(() => systemApi(`/groups/${id.value}${path}`, { method: path === '' ? 'PUT' : 'POST', body })).catch(ignore);
    if (res) {
        fill(res);
        toast(message);
    }
    return res;
}
const save = () => act('', { ...edit, ai_monthly_quota: edit.ai_monthly_quota === '' ? null : edit.ai_monthly_quota }, 'Đã lưu.');
const review = (status) => act('/review', { status }, status === 'approved' ? 'Đã duyệt nhóm.' : 'Đã từ chối nhóm.');
async function lock() {
    const locked = g.value.service_status !== 'locked';
    if (await act('/lock', { locked, reason: lockReason.value || null }, locked ? 'Đã khóa nhóm, đã báo email cho nhóm.' : 'Đã mở khóa nhóm.')) {
        lockOpen.value = false;
        lockReason.value = '';
    }
}
async function destroy() {
    const ok = await submit(() => systemApi(`/groups/${id.value}`, { method: 'DELETE', body: { confirm_code: confirmCode.value } }).then(() => true)).catch(ignore);
    if (ok) {
        toast('Đã xóa nhóm. Dữ liệu xóa hẳn sau 30 ngày.');
        router.push('/system/nhom');
    }
}
const restore = () => act('/restore', {}, 'Đã khôi phục nhóm.');
const reviewLabel = { pending_review: 'Chờ duyệt', approved: 'Đã duyệt', rejected: 'Từ chối' };
</script>

<template>
    <SystemShell :title="g?.name ?? 'Nhóm'">
        <p v-if="!g" class="py-10 text-center text-sm text-muted-foreground">Đang tải…</p>
        <div v-else class="grid gap-3 lg:grid-cols-2">
            <section class="card space-y-2 p-4 text-sm">
                <p class="flex flex-wrap items-center gap-2">
                    <span class="font-mono">/{{ g.code }}</span>
                    <span class="chip">{{ reviewLabel[g.review_status] }}</span>
                    <span class="chip" :class="g.service_status === 'locked' ? '!bg-expense/15 !text-expense' : '!bg-primary/15 !text-primary'">{{ g.service_status === 'locked' ? 'Đang khóa' : 'Hoạt động' }}</span>
                    <span v-if="g.deleted_at" class="chip !bg-expense/15 !text-expense">Đã xóa {{ formatDateTime(g.deleted_at) }}</span>
                    <a v-if="!g.deleted_at" :href="`/${g.code}`" target="_blank" class="ml-auto inline-flex items-center gap-1 text-primary">Mở trang nhóm <ExternalLink class="size-3" /></a>
                </p>
                <p v-if="g.lock_reason" class="text-expense">Lý do khóa: {{ g.lock_reason }}</p>
                <p><span class="text-muted-foreground">Trưởng nhóm:</span> {{ g.owner?.name }} · <a :href="`tel:${g.owner?.phone}`" class="text-primary">{{ g.owner?.phone }}</a><template v-if="g.owner?.email"> · {{ g.owner.email }}</template></p>
                <p><span class="text-muted-foreground">Danh bạ:</span> {{ g.counts.contacts }} người · {{ g.counts.accounts }} tài khoản · {{ g.counts.transactions }} phiếu</p>
                <p><span class="text-muted-foreground">Số dư:</span> {{ g.balance === null ? '—' : formatMoney(g.balance) }} · <span class="text-muted-foreground">Ảnh:</span> {{ g.storage_mb }} MB</p>
                <p><span class="text-muted-foreground">Tạo lúc:</span> {{ formatDateTime(g.created_at) }}<template v-if="g.reviewed_at"> · duyệt {{ formatDateTime(g.reviewed_at) }}</template></p>
                <div v-if="!g.deleted_at" class="flex flex-wrap gap-2 pt-2">
                    <button v-if="g.review_status !== 'approved'" type="button" class="btn-primary !min-h-10" @click="review('approved')">Duyệt</button>
                    <button v-if="g.review_status !== 'rejected'" type="button" class="btn-outline !min-h-10" @click="review('rejected')">Từ chối</button>
                    <button type="button" class="btn-outline !min-h-10" :class="{ 'text-expense': g.service_status !== 'locked' }" @click="lockOpen = true">{{ g.service_status === 'locked' ? 'Mở khóa' : 'Khóa nhóm' }}</button>
                </div>
                <button v-else type="button" class="btn-primary mt-2 !min-h-10" @click="restore">Khôi phục nhóm</button>
            </section>

            <section class="card p-4 text-sm">
                <h2 class="font-semibold">AI đọc bill 6 tháng gần đây</h2>
                <table class="mt-2 w-full">
                    <tbody>
                        <tr v-for="m in g.ai_usage" :key="m.month" class="border-t border-border"><td class="py-1.5">{{ m.month }}</td><td class="py-1.5 text-right">{{ m.scans }} lượt</td><td class="py-1.5 text-right">${{ m.cost_usd }}</td></tr>
                        <tr v-if="!g.ai_usage.length"><td class="py-1.5 text-muted-foreground">Chưa dùng.</td></tr>
                    </tbody>
                </table>
            </section>

            <form v-if="!g.deleted_at" class="card space-y-3 p-4 lg:col-span-2" @submit.prevent="save">
                <h2 class="font-semibold">Sửa nhóm</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <FormField v-model="edit.name" label="Tên nhóm" required :error="state.errors.name" />
                    <FormField v-model="edit.code" label="Mã nhóm" required :error="state.errors.code" hint="Đổi mã làm hỏng link đã chia sẻ và webhook SePay của nhóm." />
                    <FormField label="Chế độ xem"><select v-model="edit.visibility" class="field"><option value="public">Công khai</option><option value="private">Riêng tư</option></select></FormField>
                    <FormField v-model="edit.ai_monthly_quota" type="number" min="0" label="Hạn mức AI / tháng" hint="Để trống: không giới hạn" :error="state.errors.ai_monthly_quota" />
                </div>
                <FormField label="Ghi chú nội bộ"><textarea v-model="edit.note" rows="2" class="field"></textarea></FormField>
                <div class="flex flex-wrap gap-2">
                    <button class="btn-primary" :disabled="state.busy">Lưu</button>
                    <button type="button" class="btn-outline ml-auto text-expense" @click="deleteOpen = true">Xóa nhóm</button>
                </div>
            </form>
        </div>

        <Sheet :open="lockOpen" :title="g?.service_status === 'locked' ? 'Mở khóa nhóm' : 'Khóa nhóm'" @close="lockOpen = false">
            <p class="text-sm text-muted-foreground">{{ g?.service_status === 'locked' ? 'Nhóm hoạt động lại bình thường, trưởng nhóm nhận email báo.' : 'Trang nhóm và webhook SePay sẽ trả "Nhóm đang tạm khóa". Dữ liệu vẫn giữ nguyên. Trưởng nhóm nhận email kèm lý do.' }}</p>
            <FormField v-if="g?.service_status !== 'locked'" v-model="lockReason" label="Lý do" required class="mt-3" :error="state.errors.reason" />
            <button type="button" class="mt-4 w-full" :class="g?.service_status === 'locked' ? 'btn-primary' : 'btn-danger'" :disabled="state.busy" @click="lock">{{ g?.service_status === 'locked' ? 'Mở khóa' : 'Khóa nhóm' }}</button>
        </Sheet>
        <Sheet :open="deleteOpen" title="Xóa nhóm" @close="deleteOpen = false">
            <p class="text-sm text-muted-foreground">Nhóm biến mất ngay, khôi phục được trong 30 ngày, sau đó xóa hẳn cả ảnh. Gõ lại mã nhóm <b class="font-mono">{{ g?.code }}</b> để xác nhận.</p>
            <FormField v-model="confirmCode" label="Mã nhóm" class="mt-3" :error="state.errors.confirm_code" />
            <button type="button" class="btn-danger mt-4 w-full" :disabled="state.busy" @click="destroy">Xóa nhóm</button>
        </Sheet>
    </SystemShell>
</template>
