<script setup>
import { computed, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AuthLayout from '../../layouts/AuthLayout.vue';
import FormField from '../../components/FormField.vue';
import { api } from '../../lib/api.js';
import { ignore, useForm } from '../../lib/form.js';
import { nextPath, withNext } from '../../lib/next.js';
import { homePath, setMe } from '../../lib/session.js';

const route = useRoute();
const router = useRouter();
const next = computed(() => nextPath(route));
const form = reactive({ phone: '', password: '' });
const { state, submit } = useForm();

async function login() {
    const res = await submit(() => api('/login', { method: 'POST', body: { ...form, remember: true } })).catch(ignore);
    if (!res) return;
    setMe(res.data);
    router.replace(res.data.must_change_password ? '/change-password' : next.value || homePath(res.data));
}
</script>

<template>
    <AuthLayout title="Đăng nhập" subtitle="Dùng số điện thoại và mật khẩu WePo.">
        <form class="space-y-4" @submit.prevent="login">
            <FormField v-model="form.phone" label="Số điện thoại" type="tel" inputmode="tel" autocomplete="username" :error="state.errors.phone" required />
            <FormField v-model="form.password" label="Mật khẩu" type="password" autocomplete="current-password" :error="state.errors.password" required />
            <button class="btn-primary w-full" :disabled="state.busy">{{ state.busy ? 'Đang đăng nhập...' : 'Đăng nhập' }}</button>
        </form>
        <div class="mt-6 flex justify-between text-sm">
            <RouterLink to="/forgot-password" class="font-medium text-primary">Quên mật khẩu?</RouterLink>
            <RouterLink :to="withNext('/register', next)" class="font-medium text-primary">Tạo tài khoản</RouterLink>
        </div>
    </AuthLayout>
</template>
