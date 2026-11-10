<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminShell from '../../layouts/AdminShell.vue';
import FormField from '../../components/FormField.vue';
import MoneyInput from '../../components/MoneyInput.vue';
import { adminApi } from '../../lib/api.js';
import { useForm, ignore } from '../../lib/form.js';
import { fold } from '../../lib/text.js';
import { toast } from '../../lib/notice.js';

// Tạo/sửa chiến dịch và danh sách phải đóng, xem plan/05 mục 1.
const route = useRoute();
const router = useRouter();
const code = computed(() => route.params.group);
const id = computed(() => (route.params.id ? Number(route.params.id) : null));
const options = ref(null);
const lockedCode = ref(false);
const { state, submit } = useForm();
const year = new Date().getFullYear();

const form = reactive({
    name: `Thu quỹ năm ${year}`,
    category_id: null,
    amount_per_member: null,
    target_amount: null,
    starts_on: new Date().toISOString().slice(0, 10),
    ends_on: `${year}-12-31`,
    transfer_code: '',
    description: '',
    status: 'open',
});
// Danh sách phải đóng: contact_id → { on, exempt, expected_amount }
const members = reactive({});
const search = ref('');

onMounted(async () => {
    options.value = await adminApi(code.value, '/campaigns/options');
    form.category_id = options.value.categories.find((c) => c.name === 'Đóng quỹ năm')?.id ?? options.value.categories[0]?.id ?? null;
    form.transfer_code = options.value.suggested_code;
    for (const c of options.value.contacts) members[c.id] = { on: true, exempt: false, expected_amount: null };

    if (id.value) {
        const d = await adminApi(code.value, `/campaigns/${id.value}`);
        Object.assign(form, {
            name: d.name, category_id: d.category_id, amount_per_member: d.amount_per_member, target_amount: d.target_amount,
            starts_on: d.starts_on, ends_on: d.ends_on, transfer_code: d.transfer_code, description: d.description ?? '', status: d.status,
        });
        lockedCode.value = d.locked_code;
        for (const key of Object.keys(members)) members[key].on = false;
        for (const p of d.people.filter((x) => x.in_list && x.contact_id)) {
            members[p.contact_id] = { on: true, exempt: p.status === 'exempt', expected_amount: p.member_expected_amount };
        }
    }
});

const shown = computed(() => {
    const q = fold(search.value);
    return (options.value?.contacts ?? []).filter((c) => !q || fold(c.full_name).includes(q));
});
const selectedCount = computed(() => Object.values(members).filter((m) => m.on && !m.exempt).length);
const allOn = computed(() => shown.value.every((c) => members[c.id]?.on));
function toggleAll() {
    const value = !allOn.value;
    for (const c of shown.value) members[c.id].on = value;
}

async function save() {
    const body = {
        ...form,
        transfer_code: form.transfer_code || null,
        members: Object.entries(members).filter(([, m]) => m.on).map(([contactId, m]) => ({ contact_id: Number(contactId), exempt: m.exempt, expected_amount: m.expected_amount || null })),
    };
    const saved = await submit(() => adminApi(code.value, id.value ? `/campaigns/${id.value}` : '/campaigns', { method: id.value ? 'PUT' : 'POST', body })).catch(ignore);
    if (!saved) return;
    toast(id.value ? 'Đã lưu chiến dịch.' : 'Đã tạo chiến dịch.');
    router.push(`/${code.value}/quan-tri/chien-dich/${saved.id}`);
}
</script>

<template>
    <AdminShell :title="id ? 'Sửa chiến dịch' : 'Chiến dịch mới'">
        <p v-if="!options" class="py-10 text-center text-sm text-muted-foreground">Đang tải…</p>
        <form v-else class="mx-auto max-w-xl space-y-4 pb-24 lg:pb-0" @submit.prevent="save">
            <p v-if="!options.has_bank" class="rounded-xl border border-amber-400/60 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                Nhóm chưa có tài khoản nhận quỹ nên trang đóng quỹ chưa hiện mã QR.
                <RouterLink :to="`/${code}/quan-tri/cai-dat`" class="font-semibold underline">Thêm trong Cài đặt</RouterLink>
            </p>
            <FormField v-model="form.name" label="Tên chiến dịch" required maxlength="150" :error="state.errors.name" />
            <FormField label="Loại thu" required :error="state.errors.category_id">
                <select v-model="form.category_id" class="field">
                    <option v-for="c in options.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </FormField>
            <div class="grid grid-cols-2 gap-2">
                <FormField label="Mỗi người" hint="Để trống: tùy tâm" :error="state.errors.amount_per_member"><MoneyInput v-model="form.amount_per_member" /></FormField>
                <FormField label="Mục tiêu tổng" :error="state.errors.target_amount"><MoneyInput v-model="form.target_amount" /></FormField>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <FormField v-model="form.starts_on" type="date" label="Từ ngày" :error="state.errors.starts_on" />
                <FormField v-model="form.ends_on" type="date" label="Đến ngày" :error="state.errors.ends_on" />
            </div>
            <FormField label="Mã nội dung chuyển khoản" :error="state.errors.transfer_code" :hint="lockedCode ? 'Đã có giao dịch nên không đổi được nữa.' : 'Chữ không dấu và số, viết liền. Người đóng ghi mã này + tên mình vào nội dung chuyển khoản.'">
                <input v-model="form.transfer_code" class="field font-mono uppercase" maxlength="12" :disabled="lockedCode" @input="form.transfer_code = $event.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '')" />
            </FormField>
            <FormField label="Mô tả" :error="state.errors.description"><textarea v-model="form.description" rows="2" class="field" maxlength="5000"></textarea></FormField>
            <FormField v-if="id" label="Trạng thái">
                <select v-model="form.status" class="field"><option value="open">Đang mở (hiện QR)</option><option value="closed">Đã đóng (chỉ xem kết quả)</option></select>
            </FormField>

            <section class="card p-3">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-semibold">Danh sách phải đóng <span class="font-normal text-muted-foreground">({{ selectedCount }} người)</span></h2>
                    <button type="button" class="text-sm font-medium text-primary" @click="toggleAll">{{ allOn ? 'Bỏ chọn hết' : 'Chọn hết' }}</button>
                </div>
                <input v-model="search" type="search" class="field mt-2" placeholder="Tìm tên" />
                <ul class="mt-2 max-h-96 divide-y divide-border overflow-y-auto">
                    <li v-for="c in shown" :key="c.id" class="flex items-center gap-2 py-1.5">
                        <label class="flex min-h-10 min-w-0 flex-1 items-center gap-2">
                            <input v-model="members[c.id].on" type="checkbox" class="size-5 accent-[var(--primary)]" />
                            <span class="truncate" :class="{ 'text-muted-foreground line-through': members[c.id].on && members[c.id].exempt }">{{ c.full_name }}</span>
                        </label>
                        <template v-if="members[c.id].on">
                            <label class="flex items-center gap-1 text-xs text-muted-foreground"><input v-model="members[c.id].exempt" type="checkbox" class="size-4" /> Miễn</label>
                            <MoneyInput v-if="!members[c.id].exempt" v-model="members[c.id].expected_amount" class="!min-h-9 !w-28 !py-1 !text-sm" :placeholder="form.amount_per_member ? String(form.amount_per_member) : 'tùy'" aria-label="Số tiền riêng" />
                        </template>
                    </li>
                </ul>
                <p class="mt-2 text-xs text-muted-foreground">Ô số tiền riêng để trống thì dùng số tiền mỗi người ở trên.</p>
            </section>

            <div class="fixed inset-x-0 bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-20 border-t border-border bg-card p-3 lg:static lg:border-0 lg:bg-transparent lg:p-0">
                <button type="submit" class="btn-primary w-full" :disabled="state.busy">{{ state.busy ? 'Đang lưu…' : id ? 'Lưu chiến dịch' : 'Tạo chiến dịch' }}</button>
            </div>
        </form>
    </AdminShell>
</template>
