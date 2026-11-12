<script setup>
import { computed, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AuthLayout from '../../layouts/AuthLayout.vue';
import FormField from '../../components/FormField.vue';
import { api } from '../../lib/api.js';
import { ignore, useForm } from '../../lib/form.js';
import { nextPath, withNext } from '../../lib/next.js';
import { setMe } from '../../lib/session.js';

// Đăng ký tài khoản, không kèm nhóm. Tạo nhóm ở /groups/new, xem plan/11.
const route = useRoute();
const router = useRouter();
const next = computed(() => nextPath(route));
const form = reactive({ name: '', phone: '', email: '', password: '', website: '' });
const { state, submit } = useForm();
const phoneTaken = computed(() => (state.errors.phone ?? '').includes('đã có tài khoản'));

async function register() {
    const res = await submit(() => api('/register', { method: 'POST', body: form })).catch(ignore);
    if (!res) return;
    setMe(res.me);
    router.replace(next.value || '/groups');
}
</script>

<template>
    <AuthLayout title="Tạo tài khoản miễn phí" subtitle="Dùng cho mọi hội nhóm, quỹ lớp bạn tham gia">
        <!-- Người được gửi nhầm link đăng ký chung thay cho link nhóm (plan/30 mục 1) -->
        <p v-if="!next" class="mb-4 rounded-xl bg-muted px-3 py-2 text-sm text-muted-foreground">Muốn vào nhóm có sẵn? Mở <span class="font-medium text-foreground">link nhóm</span> do ban quản trị gửi để vừa tạo tài khoản vừa xin vào nhóm.</p>
        <form class="space-y-4" @submit.prevent="register">
            <FormField v-model="form.name" label="Họ tên" autocomplete="name" :error="state.errors.name" required />
            <div>
                <FormField v-model="form.phone" label="Số điện thoại" type="tel" inputmode="tel" autocomplete="tel" hint="Dùng để đăng nhập. Được mã hóa khi lưu." :error="state.errors.phone" required />
                <p v-if="phoneTaken" class="mt-1 text-sm">
                    <RouterLink :to="withNext('/login', next)" class="font-medium text-primary">Đăng nhập</RouterLink>
                    <span class="text-muted-foreground"> hoặc </span>
                    <RouterLink to="/forgot-password" class="font-medium text-primary">Quên mật khẩu?</RouterLink>
                </p>
            </div>
            <FormField v-model="form.email" label="Email" type="email" autocomplete="email" hint="Không bắt buộc. Có email thì bạn tự lấy lại được mật khẩu." :error="state.errors.email" />
            <FormField v-model="form.password" label="Mật khẩu" type="password" autocomplete="new-password" hint="Ít nhất 8 ký tự." :error="state.errors.password" required />

            <input v-model="form.website" type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true" />

            <button class="btn-primary w-full" :disabled="state.busy">{{ state.busy ? 'Đang tạo tài khoản...' : 'Tạo tài khoản' }}</button>
            <p class="text-center text-sm text-muted-foreground">
                Đã có tài khoản? <RouterLink :to="withNext('/login', next)" class="font-medium text-primary">Đăng nhập</RouterLink>
            </p>
        </form>
    </AuthLayout>
</template>
