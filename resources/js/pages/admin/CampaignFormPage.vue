<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminShell from '../../layouts/AdminShell.vue';
import FormField from '../../components/FormField.vue';
import MoneyInput from '../../components/MoneyInput.vue';
import SwitchToggle from '../../components/SwitchToggle.vue';
import { adminApi } from '../../lib/api.js';
import { useForm, ignore } from '../../lib/form.js';
import { fold } from '../../lib/text.js';
import { toast } from '../../lib/notice.js';

// Tạo/sửa chiến dịch. Mọi người đang tham gia đều phải đóng, chỉ chọn người được miễn. Xem plan/05 mục 1.
const route = useRoute();
const router = useRouter();
const code = computed(() => route.params.group);
const id = computed(() => (route.params.id ? Number(route.params.id) : null));
const options = ref(null);
const lockedCode = ref(false);
const { state, submit } = useForm();

const form = reactive({
    name: `Thu quỹ năm ${new Date().getFullYear()}`,
    category_id: null,
    amount_per_member: null,
    transfer_code: '',
    description: '',
    status: 'open',
});
const exempt = reactive(new Set());
const savedName = ref('');
const crumbs = computed(() => [
    { label: 'Chiến dịch', to: `/${code.value}/admin/campaigns` },
    ...(id.value ? [{ label: savedName.value || 'Chi tiết', to: `/${code.value}/admin/campaigns/${id.value}` }] : []),
]);
const search = ref('');
// Phần chọn người được miễn mặc định ẩn; tắt đi thì không ai được miễn.
const hasExempt = ref(false);

onMounted(async () => {
    options.value = await adminApi(code.value, '/campaigns/options');
    form.category_id = options.value.categories.find((c) => c.name === 'Đóng quỹ năm')?.id ?? options.value.categories[0]?.id ?? null;

    if (id.value) {
        const d = await adminApi(code.value, `/campaigns/${id.value}`);
        Object.assign(form, { name: d.name, category_id: d.category_id, amount_per_member: d.amount_per_member, transfer_code: d.transfer_code, description: d.description ?? '', status: d.status });
        lockedCode.value = d.locked_code;
        savedName.value = d.name;
        d.people.filter((p) => p.status === 'exempt' && p.contact_id).forEach((p) => exempt.add(p.contact_id));
        hasExempt.value = exempt.size > 0;
    }
});

const shown = computed(() => {
    const q = fold(search.value);
    return (options.value?.contacts ?? []).filter((c) => !q || fold(c.full_name).includes(q));
});
const payingCount = computed(() => (options.value?.contacts.length ?? 0) - (hasExempt.value ? [...exempt].filter((cid) => options.value?.contacts.some((c) => c.id === cid)).length : 0));

function toggle(contactId) {
    if (exempt.has(contactId)) exempt.delete(contactId);
    else exempt.add(contactId);
}

async function save() {
    const body = {
        name: form.name,
        category_id: form.category_id,
        amount_per_member: form.amount_per_member,
        transfer_code: form.transfer_code || null,
        description: form.description || null,
        exempt_ids: hasExempt.value ? [...exempt] : [],
        ...(id.value ? { status: form.status } : {}),
    };
    const saved = await submit(() => adminApi(code.value, id.value ? `/campaigns/${id.value}` : '/campaigns', { method: id.value ? 'PUT' : 'POST', body })).catch(ignore);
    if (!saved) return;
    toast(id.value ? 'Đã lưu chiến dịch.' : `Đã tạo chiến dịch, mã chuyển khoản ${saved.transfer_code}.`);
    router.push(`/${code.value}/admin/campaigns/${saved.id}`);
}
</script>

<template>
    <AdminShell :title="id ? 'Sửa chiến dịch' : 'Chiến dịch mới'" :crumbs="crumbs">
        <p v-if="!options" class="py-10 text-center text-sm text-muted-foreground">Đang tải…</p>
        <form v-else class="mx-auto max-w-xl space-y-4 pb-24 lg:pb-0" @submit.prevent="save">
            <p v-if="!options.has_bank" class="rounded-xl border border-amber-400/60 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                Nhóm chưa có tài khoản nhận quỹ nên trang đóng quỹ chưa hiện mã QR.
                <RouterLink :to="`/${code}/admin/settings`" class="font-semibold underline">Thêm trong Cài đặt</RouterLink>
            </p>
            <FormField v-model="form.name" label="Tên chiến dịch" required maxlength="150" :error="state.errors.name" />
            <FormField label="Loại thu" required :error="state.errors.category_id">
                <select v-model="form.category_id" class="field" required>
                    <option v-for="c in options.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </FormField>
            <FormField label="Số tiền mỗi người" hint="Để trống: đóng tùy tâm" :error="state.errors.amount_per_member"><MoneyInput v-model="form.amount_per_member" /></FormField>
            <FormField label="Mã nội dung chuyển khoản" :error="state.errors.transfer_code" :hint="lockedCode ? 'Đã có giao dịch nên không đổi được nữa.' : `Không bắt buộc. Để trống thì tự tạo, vd ${options.suggested_code}. Người đóng ghi mã này + tên mình vào nội dung chuyển khoản.`">
                <input v-model="form.transfer_code" class="field font-mono uppercase" maxlength="12" :placeholder="id ? '' : options.suggested_code" :disabled="lockedCode" @input="form.transfer_code = $event.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '')" />
            </FormField>
            <FormField label="Mô tả" :error="state.errors.description"><textarea v-model="form.description" rows="2" class="field" maxlength="5000"></textarea></FormField>
            <FormField v-if="id" label="Trạng thái">
                <select v-model="form.status" class="field"><option value="open">Đang mở (hiện QR)</option><option value="closed">Đã đóng (chỉ xem kết quả)</option></select>
            </FormField>

            <section class="card p-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">Có người được miễn<template v-if="hasExempt && exempt.size"> <span class="font-normal text-muted-foreground">({{ exempt.size }})</span></template></h2>
                        <p class="text-sm text-muted-foreground">{{ payingCount }} người trong danh bạ phải đóng. Người thêm vào danh bạ sau cũng tự phải đóng.</p>
                    </div>
                    <SwitchToggle v-model="hasExempt" label="Có người được miễn" />
                </div>
                <template v-if="hasExempt">
                <input v-model="search" type="search" class="field mt-2" placeholder="Tìm tên" />
                <ul class="mt-2 max-h-80 divide-y divide-border overflow-y-auto">
                    <li v-for="c in shown" :key="c.id">
                        <label class="flex min-h-11 items-center gap-3">
                            <input type="checkbox" class="size-5 accent-[var(--primary)]" :checked="exempt.has(c.id)" @change="toggle(c.id)" />
                            <span class="truncate">{{ c.full_name }}</span>
                            <span v-if="exempt.has(c.id)" class="chip ml-auto shrink-0">Miễn</span>
                        </label>
                    </li>
                    <li v-if="!shown.length" class="py-3 text-center text-sm text-muted-foreground">Không có ai.</li>
                </ul>
                </template>
            </section>

            <div class="fixed inset-x-0 bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-20 border-t border-border bg-card p-3 lg:static lg:border-0 lg:bg-transparent lg:p-0">
                <button type="submit" class="btn-primary w-full" :disabled="state.busy">{{ state.busy ? 'Đang lưu…' : id ? 'Lưu chiến dịch' : 'Tạo chiến dịch' }}</button>
            </div>
        </form>
    </AdminShell>
</template>
