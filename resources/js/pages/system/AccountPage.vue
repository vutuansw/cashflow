<script setup>
import { reactive } from 'vue';
import SystemShell from '../../layouts/SystemShell.vue';
import FormField from '../../components/FormField.vue';
import { systemApi, systemSession } from '../../lib/systemApi.js';
import { ignore, useForm } from '../../lib/form.js';
import { toast } from '../../lib/notice.js';

// Tài khoản của quản trị viên /system: xem thông tin, tự đổi mật khẩu (plan/10).
const form = reactive({ current_password: '', password: '', password_confirmation: '' });
const { state, submit } = useForm();

async function save() {
    const res = await submit(() => systemApi('/me/password', { method: 'PUT', body: form })).catch(ignore);
    if (!res) return;
    systemSession.admin = res.data;
    if (res.csrf) systemSession.csrf = res.csrf;
    form.current_password = form.password = form.password_confirmation = '';
    toast('Đã đổi mật khẩu. Các phiên đăng nhập khác đã bị đăng xuất.');
}
</script>

<template>
    <SystemShell title="Tài khoản của tôi">
        <div class="max-w-lg space-y-4">
            <section class="card p-4 text-sm">
                <p class="text-muted-foreground">Họ tên</p>
                <p class="font-medium">{{ systemSession.admin?.name }}</p>
                <p class="mt-3 text-muted-foreground">Email</p>
                <p class="font-medium">{{ systemSession.admin?.email }}</p>
            </section>
            <section class="card p-4">
                <h2 class="font-semibold">Đổi mật khẩu</h2>
                <p class="mt-1 text-sm text-muted-foreground">Đổi xong, các máy khác đang đăng nhập tài khoản này sẽ bị đăng xuất.</p>
                <form class="mt-4 space-y-4" @submit.prevent="save">
                    <FormField v-model="form.current_password" label="Mật khẩu hiện tại" type="password" autocomplete="current-password" :error="state.errors.current_password" required />
                    <FormField v-model="form.password" label="Mật khẩu mới" type="password" autocomplete="new-password" hint="Ít nhất 8 ký tự." :error="state.errors.password" required />
                    <FormField v-model="form.password_confirmation" label="Nhập lại mật khẩu mới" type="password" autocomplete="new-password" required />
                    <button class="btn-primary w-full" :disabled="state.busy">Lưu mật khẩu</button>
                </form>
            </section>
        </div>
    </SystemShell>
</template>
