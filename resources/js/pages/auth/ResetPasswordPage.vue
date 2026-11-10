<script setup>
import { reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AuthLayout from '../../layouts/AuthLayout.vue';
import FormField from '../../components/FormField.vue';
import { api } from '../../lib/api.js';
import { ignore, useForm } from '../../lib/form.js';
import { toast } from '../../lib/notice.js';

const route = useRoute();
const router = useRouter();
const form = reactive({ token: route.query.token || '', email: route.query.email || '', password: '', password_confirmation: '' });
const { state, submit } = useForm();

async function reset() {
    const res = await submit(() => api('/reset-password', { method: 'POST', body: form })).catch(ignore);
    if (!res) return;
    toast('Đã đặt lại mật khẩu, bạn đăng nhập lại nhé.');
    router.replace('/login');
}
</script>

<template>
    <AuthLayout title="Đặt mật khẩu mới">
        <form class="space-y-4" @submit.prevent="reset">
            <p v-if="state.errors.email" class="text-sm text-expense">{{ state.errors.email }}</p>
            <FormField v-model="form.password" label="Mật khẩu mới" type="password" autocomplete="new-password" hint="Ít nhất 8 ký tự." :error="state.errors.password" required />
            <FormField v-model="form.password_confirmation" label="Nhập lại mật khẩu mới" type="password" autocomplete="new-password" required />
            <button class="btn-primary w-full" :disabled="state.busy">Lưu mật khẩu</button>
        </form>
    </AuthLayout>
</template>
