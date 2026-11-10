<script setup>
import { reactive, ref, watch } from 'vue';
import FormField from '../../components/FormField.vue';
import MoneyInput from '../../components/MoneyInput.vue';
import SystemShell from '../../layouts/SystemShell.vue';
import { systemApi } from '../../lib/systemApi.js';
import { api } from '../../lib/api.js';
import { useForm, ignore } from '../../lib/form.js';
import { copyText } from '../../lib/clipboard.js';

// Tạo nhóm hộ khách: trưởng nhóm + nhóm, duyệt luôn; tài khoản mới có mật khẩu tạm hiện một lần (plan/02 mục 6).
const form = reactive({ owner_name: '', owner_phone: '', owner_email: '', name: '', code: '', visibility: 'public', opening_balance: 0 });
const codeTouched = ref(false);
const result = ref(null);
const { state, submit } = useForm();

let timer;
watch(() => form.name, (name) => {
    if (codeTouched.value || !name.trim()) return;
    clearTimeout(timer);
    timer = setTimeout(async () => {
        const r = await api(`/group-code?${new URLSearchParams({ name })}`).catch(() => null);
        if (r?.suggestion && !codeTouched.value) form.code = r.suggestion;
    }, 300);
});

async function create() {
    const res = await submit(() => systemApi('/groups', { method: 'POST', body: { ...form, owner_email: form.owner_email || null } })).catch(ignore);
    if (res) result.value = res;
}
const message = () => `Nhóm "${result.value.group.name}" đã được tạo trên WePo.\nĐăng nhập: ${result.value.login_url}\nSĐT: ${form.owner_phone}\nMật khẩu tạm: ${result.value.temp_password}\n(Đổi mật khẩu ở lần đăng nhập đầu.)`;
</script>

<template>
    <SystemShell title="Tạo nhóm hộ khách" :crumbs="[{ label: 'Nhóm', to: '/system/groups' }]">
        <div v-if="result" class="card mx-auto max-w-xl space-y-3 p-4">
            <p class="font-semibold">Đã tạo nhóm {{ result.group.name }} (/{{ result.group.code }})</p>
            <template v-if="result.temp_password">
                <p class="text-sm">Mật khẩu tạm của trưởng nhóm, chỉ hiện một lần:</p>
                <p class="rounded-xl bg-muted p-3 text-center font-mono text-xl tracking-widest">{{ result.temp_password }}</p>
                <button type="button" class="btn-primary w-full" @click="copyText(message(), 'Đã chép lời nhắn, gửi qua Zalo cho trưởng nhóm.')">Chép lời nhắn gửi trưởng nhóm</button>
            </template>
            <p v-else class="text-sm text-muted-foreground">SĐT này đã có tài khoản WePo nên nhóm mới được gắn vào tài khoản đó, mật khẩu không đổi.</p>
            <RouterLink :to="`/system/groups/${result.group.id}`" class="btn-outline w-full">Xem nhóm</RouterLink>
        </div>
        <form v-else class="mx-auto max-w-xl space-y-4" @submit.prevent="create">
            <section class="card space-y-3 p-4">
                <h2 class="font-semibold">Trưởng nhóm</h2>
                <FormField v-model="form.owner_name" label="Họ tên" required :error="state.errors.owner_name" />
                <FormField v-model="form.owner_phone" label="SĐT" inputmode="tel" required :error="state.errors.owner_phone" hint="Đã có tài khoản thì gắn nhóm vào tài khoản đó." />
                <FormField v-model="form.owner_email" type="email" label="Email" :error="state.errors.owner_email" />
            </section>
            <section class="card space-y-3 p-4">
                <h2 class="font-semibold">Nhóm</h2>
                <FormField v-model="form.name" label="Tên nhóm" required :error="state.errors.name" />
                <FormField label="Mã nhóm" required :error="state.errors.code"><input v-model="form.code" class="field font-mono" @input="codeTouched = true" /></FormField>
                <FormField label="Chế độ xem"><select v-model="form.visibility" class="field"><option value="public">Công khai</option><option value="private">Riêng tư</option></select></FormField>
                <FormField label="Số dư quỹ hiện có"><MoneyInput v-model="form.opening_balance" /></FormField>
            </section>
            <button class="btn-primary w-full" :disabled="state.busy">Tạo nhóm</button>
        </form>
    </SystemShell>
</template>
