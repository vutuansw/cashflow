<script setup>
import { computed, reactive } from 'vue';
import { useRouter } from 'vue-router';
import AuthLayout from '../../layouts/AuthLayout.vue';
import FormField from '../../components/FormField.vue';
import { api } from '../../lib/api.js';
import { ignore, useForm } from '../../lib/form.js';
import { toast } from '../../lib/notice.js';
import { homePath, logout, session, setMe } from '../../lib/session.js';

const router = useRouter();
const forced = computed(() => session.me?.must_change_password);
const form = reactive({ current_password: '', password: '', password_confirmation: '' });
const { state, submit } = useForm();

async function save() {
    const res = await submit(() => api('/me/password', { method: 'PUT', body: form })).catch(ignore);
    if (!res) return;
    setMe(res.data);
    toast('Đã đổi mật khẩu.');
    router.replace(homePath(res.data));
}

async function signOut() {
    await logout();
    router.replace('/login');
}
</script>

<template>
    <AuthLayout title="Đổi mật khẩu" :subtitle="forced ? 'Bạn đang dùng mật khẩu tạm. Đặt mật khẩu của riêng bạn để tiếp tục.' : ''">
        <form class="space-y-4" @submit.prevent="save">
            <FormField v-if="!forced" v-model="form.current_password" label="Mật khẩu hiện tại" type="password" autocomplete="current-password" :error="state.errors.current_password" required />
            <FormField v-model="form.password" label="Mật khẩu mới" type="password" autocomplete="new-password" hint="Ít nhất 8 ký tự." :error="state.errors.password" required />
            <FormField v-model="form.password_confirmation" label="Nhập lại mật khẩu mới" type="password" autocomplete="new-password" required />
            <button class="btn-primary w-full" :disabled="state.busy">Lưu mật khẩu</button>
        </form>
        <button v-if="forced" type="button" class="mt-6 text-sm font-medium text-muted-foreground" @click="signOut">Đăng xuất</button>
        <button v-else type="button" class="mt-6 text-sm font-medium text-primary" @click="router.back()">Quay lại</button>
    </AuthLayout>
</template>
