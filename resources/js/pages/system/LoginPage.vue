<script setup>
import { reactive } from 'vue';
import { useRouter } from 'vue-router';
import FormField from '../../components/FormField.vue';
import { useForm, ignore } from '../../lib/form.js';
import { systemApi, systemSession } from '../../lib/systemApi.js';

// Đăng nhập /system bằng email + mật khẩu (plan/02 mục 6).
const router = useRouter();
const form = reactive({ email: '', password: '' });
const { state, submit } = useForm();

async function login() {
    const res = await submit(() => systemApi('/login', { method: 'POST', body: form })).catch(ignore);
    if (!res) return;
    systemSession.admin = res.data;
    systemSession.csrf = res.csrf;
    router.replace(router.currentRoute.value.query.next || '/system');
}
</script>

<template>
    <main class="mx-auto flex min-h-dvh max-w-sm flex-col justify-center px-4">
        <h1 class="text-xl font-semibold">Quản trị Quỹ nhóm</h1>
        <p class="mt-1 text-sm text-muted-foreground">Dành cho nhân sự HIPOTECH.</p>
        <form class="mt-6 space-y-4" @submit.prevent="login">
            <FormField v-model="form.email" type="email" label="Email" autocomplete="username" required :error="state.errors.email" />
            <FormField v-model="form.password" type="password" label="Mật khẩu" autocomplete="current-password" required :error="state.errors.password" />
            <button class="btn-primary w-full" :disabled="state.busy">Đăng nhập</button>
        </form>
    </main>
</template>
