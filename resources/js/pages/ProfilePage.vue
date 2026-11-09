<script setup>
import { reactive } from 'vue';
import { useRouter } from 'vue-router';
import AuthLayout from '../layouts/AuthLayout.vue';
import FormField from '../components/FormField.vue';
import { api } from '../lib/api.js';
import { ignore, useForm } from '../lib/form.js';
import { toast } from '../lib/notice.js';
import { logout, session, setMe } from '../lib/session.js';

const router = useRouter();
const form = reactive({ name: session.me?.name ?? '', email: session.me?.email ?? '' });
const { state, submit } = useForm();

async function save() {
    const res = await submit(() => api('/me', { method: 'PUT', body: form })).catch(ignore);
    if (!res) return;
    setMe(res.data);
    toast('Đã lưu.');
}

async function signOut() {
    await logout();
    router.replace('/dang-nhap');
}
</script>

<template>
    <AuthLayout title="Cá nhân">
        <form class="space-y-4" @submit.prevent="save">
            <FormField label="Số điện thoại" hint="Muốn đổi số, nhờ trưởng nhóm liên hệ quản trị Quỹ nhóm.">
                <input class="field" :value="session.me?.phone" disabled />
            </FormField>
            <FormField v-model="form.name" label="Họ tên" :error="state.errors.name" required />
            <FormField v-model="form.email" label="Email" type="email" hint="Để lấy lại mật khẩu khi quên." :error="state.errors.email" />
            <button class="btn-primary w-full" :disabled="state.busy">Lưu</button>
        </form>
        <div class="mt-6 grid gap-3">
            <RouterLink to="/doi-mat-khau" class="btn-outline">Đổi mật khẩu</RouterLink>
            <RouterLink to="/nhom" class="btn-outline">Chọn nhóm</RouterLink>
            <button class="btn-outline text-expense" @click="signOut">Đăng xuất</button>
        </div>
    </AuthLayout>
</template>
