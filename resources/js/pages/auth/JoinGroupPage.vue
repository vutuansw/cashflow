<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Clock } from 'lucide-vue-next';
import AuthLayout from '../../layouts/AuthLayout.vue';
import FormField from '../../components/FormField.vue';
import { api } from '../../lib/api.js';
import { ignore, useForm } from '../../lib/form.js';
import { withNext } from '../../lib/next.js';
import { loadMe, session, setMe } from '../../lib/session.js';
import { toast } from '../../lib/notice.js';

// Link đăng ký vào nhóm /{ma-nhom}/register: tạo tài khoản rồi chờ QTV duyệt, xem plan/13.
const route = useRoute();
const router = useRouter();
const code = computed(() => route.params.group);
const here = computed(() => `/${code.value}/register`);

const info = ref(null);
const loadError = ref('');
const form = reactive({ name: '', phone: '', email: '', password: '', website: '' });
const { state, submit } = useForm();
const phoneTaken = computed(() => (state.errors.phone ?? '').includes('đã có tài khoản'));

const title = computed(() => (info.value ? `Tham gia ${info.value.name}` : 'Tham gia nhóm'));
const subtitle = computed(() => {
    if (!info.value?.enabled) return '';
    if (info.value.status === 'pending') return '';
    return session.me
        ? 'Gửi yêu cầu, quản trị viên nhóm sẽ duyệt cho bạn.'
        : 'Tạo tài khoản WePo, quản trị viên nhóm sẽ duyệt cho bạn vào nhóm.';
});

async function load() {
    try {
        info.value = await api(`/g/${encodeURIComponent(code.value)}/join`);
        if (info.value.status === 'member') router.replace(`/${code.value}`);
        document.title = `${title.value} - WePo`;
    } catch (e) {
        loadError.value = e.status === 404 ? 'Không tìm thấy nhóm này. Bạn kiểm tra lại link nhé.' : e.status === 423 ? 'Nhóm đang tạm khóa.' : e.message;
    }
}
onMounted(load);

async function register() {
    const res = await submit(() => api(`/g/${encodeURIComponent(code.value)}/join/register`, { method: 'POST', body: form })).catch(ignore);
    if (!res) return;
    setMe(res.me);
    router.replace(`/${code.value}`);
}

async function requestJoin() {
    const res = await submit(() => api(`/g/${encodeURIComponent(code.value)}/join`, { method: 'POST' })).catch(ignore);
    if (!res) {
        if (state.errors.group) toast(state.errors.group, 'error');
        return;
    }
    await loadMe(true);
    router.replace(`/${code.value}`);
}
</script>

<template>
    <AuthLayout :title="title" :subtitle="subtitle">
        <p v-if="loadError" class="card p-4 text-center">{{ loadError }}</p>
        <p v-else-if="!info" class="text-center text-muted-foreground">Đang tải...</p>

        <div v-else-if="!info.enabled" class="card p-4 text-center">
            <p class="font-medium">Nhóm này không nhận đăng ký qua link.</p>
            <p class="mt-1 text-sm text-muted-foreground">Bạn nhờ trưởng nhóm thêm số điện thoại của bạn vào nhóm.</p>
            <RouterLink :to="`/${code}`" class="btn-outline mt-4 w-full">Xem trang nhóm</RouterLink>
        </div>

        <div v-else-if="info.status === 'pending'" class="card p-4 text-center">
            <Clock class="mx-auto size-8 text-primary" />
            <p class="mt-2 font-medium">Bạn đã gửi yêu cầu vào nhóm.</p>
            <p class="mt-1 text-sm text-muted-foreground">Đang chờ quản trị viên duyệt.</p>
            <RouterLink :to="`/${code}`" class="btn-outline mt-4 w-full">Xem trang nhóm</RouterLink>
        </div>

        <!-- Đã đăng nhập bằng tài khoản WePo có sẵn: chỉ cần bấm xin vào -->
        <div v-else-if="session.me" class="space-y-4">
            <p class="card p-4 text-center text-sm">Bạn đang đăng nhập là <span class="font-semibold">{{ session.me.name }}</span>.</p>
            <button type="button" class="btn-primary w-full" :disabled="state.busy" @click="requestJoin">{{ state.busy ? 'Đang gửi...' : 'Xin vào nhóm' }}</button>
        </div>

        <form v-else class="space-y-4" @submit.prevent="register">
            <FormField v-model="form.name" label="Họ tên" autocomplete="name" :error="state.errors.name" required />
            <div>
                <FormField v-model="form.phone" label="Số điện thoại" type="tel" inputmode="tel" autocomplete="tel" hint="Dùng để đăng nhập. Được mã hóa khi lưu." :error="state.errors.phone" required />
                <RouterLink v-if="phoneTaken" :to="withNext('/login', here)" class="btn-outline mt-2 w-full">Đăng nhập để xin vào nhóm</RouterLink>
            </div>
            <FormField v-model="form.email" label="Email" type="email" autocomplete="email" hint="Không bắt buộc. Có email thì bạn tự lấy lại được mật khẩu." :error="state.errors.email" />
            <FormField v-model="form.password" label="Mật khẩu" type="password" autocomplete="new-password" hint="Ít nhất 8 ký tự." :error="state.errors.password" required />

            <input v-model="form.website" type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true" />

            <button class="btn-primary w-full" :disabled="state.busy">{{ state.busy ? 'Đang tạo tài khoản...' : 'Tạo tài khoản và xin vào nhóm' }}</button>
            <p class="text-center text-sm text-muted-foreground">
                Đã có tài khoản? <RouterLink :to="withNext('/login', here)" class="font-medium text-primary">Đăng nhập</RouterLink>
            </p>
        </form>
    </AuthLayout>
</template>
