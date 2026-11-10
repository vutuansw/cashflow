<script setup>
import { ref } from 'vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import FormField from '../../components/FormField.vue';
import { api } from '../../lib/api.js';
import { ignore, useForm } from '../../lib/form.js';

const phone = ref('');
const done = ref('');
const { state, submit } = useForm();

async function send() {
    const res = await submit(() => api('/forgot-password', { method: 'POST', body: { phone: phone.value } })).catch(ignore);
    if (res) done.value = res.message;
}
</script>

<template>
    <AuthLayout title="Quên mật khẩu" subtitle="Nhập số điện thoại bạn dùng để đăng nhập.">
        <div v-if="done" class="card p-4">{{ done }}</div>
        <form v-else class="space-y-4" @submit.prevent="send">
            <FormField v-model="phone" label="Số điện thoại" type="tel" inputmode="tel" :error="state.errors.phone" required />
            <button class="btn-primary w-full" :disabled="state.busy">Gửi yêu cầu</button>
        </form>
        <RouterLink to="/login" class="mt-6 inline-block text-sm font-medium text-primary">Quay lại đăng nhập</RouterLink>
    </AuthLayout>
</template>
