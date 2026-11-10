<script setup>
import { reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { Globe, Lock } from 'lucide-vue-next';
import AuthLayout from '../../layouts/AuthLayout.vue';
import FormField from '../../components/FormField.vue';
import MoneyInput from '../../components/MoneyInput.vue';
import { api } from '../../lib/api.js';
import { ignore, useForm } from '../../lib/form.js';
import { setMe } from '../../lib/session.js';

const router = useRouter();
const form = reactive({
    name: '', phone: '', email: '', password: '',
    group_name: '', group_code: '', visibility: 'public', opening_balance: null, website: '',
});
const { state, submit } = useForm();
const codeCheck = ref({ available: null, message: null });
const codeTouched = ref(false);

let timer;
function checkCode(withSuggestion = false) {
    clearTimeout(timer);
    timer = setTimeout(async () => {
        const params = new URLSearchParams({ code: form.group_code });
        if (withSuggestion) params.set('name', form.group_name);
        const res = await api(`/group-code?${params}`).catch(() => null);
        if (!res) return;
        if (withSuggestion && !codeTouched.value && res.suggestion) {
            form.group_code = res.suggestion;
            codeCheck.value = { available: true, message: null };
        } else {
            codeCheck.value = res;
        }
    }, 350);
}

watch(() => form.group_name, (name) => { if (!codeTouched.value && name.trim().length >= 2) checkCode(true); });

function onCodeInput(value) {
    codeTouched.value = true;
    form.group_code = value.toLowerCase().replace(/\s+/g, '-');
    if (form.group_code.length >= 3) checkCode();
}

async function register() {
    const res = await submit(() => api('/register', { method: 'POST', body: form })).catch(ignore);
    if (!res) return;
    setMe(res.me);
    router.replace(`/${res.group.code}/quan-tri?moi=1`);
}
</script>

<template>
    <AuthLayout title="Đăng ký nhóm" subtitle="Tạo tài khoản trưởng nhóm và lập quỹ cho nhóm của bạn. Miễn phí.">
        <form class="space-y-6" @submit.prevent="register">
            <section class="space-y-4">
                <h2 class="font-semibold text-muted-foreground">Bạn là ai</h2>
                <FormField v-model="form.name" label="Họ tên" autocomplete="name" :error="state.errors.name" required />
                <FormField v-model="form.phone" label="Số điện thoại" type="tel" inputmode="tel" autocomplete="tel" hint="Dùng để đăng nhập. Được mã hóa khi lưu." :error="state.errors.phone" required />
                <FormField v-model="form.email" label="Email" type="email" autocomplete="email" hint="Nhận thông báo và lấy lại mật khẩu." :error="state.errors.email" required />
                <FormField v-model="form.password" label="Mật khẩu" type="password" autocomplete="new-password" hint="Ít nhất 8 ký tự." :error="state.errors.password" required />
            </section>

            <section class="space-y-4">
                <h2 class="font-semibold text-muted-foreground">Nhóm của bạn</h2>
                <FormField v-model="form.group_name" label="Tên nhóm" placeholder="Chi đoàn A12" :error="state.errors.group_name" required />
                <FormField label="Mã nhóm" :error="state.errors.group_code || (codeCheck.available === false ? codeCheck.message : '')" required>
                    <div class="flex items-center rounded-xl border border-border bg-card focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/25">
                        <span class="pl-3 text-sm text-muted-foreground">wepo.hipotech.vn/</span>
                        <input :value="form.group_code" class="min-h-11 w-full min-w-0 bg-transparent py-2 pr-3 outline-none" autocapitalize="none" spellcheck="false" @input="onCodeInput($event.target.value)" />
                    </div>
                    <span v-if="codeCheck.available && !state.errors.group_code" class="mt-1 block text-sm text-income">Mã này dùng được.</span>
                </FormField>

                <div>
                    <span class="mb-1.5 block text-sm font-medium">Ai xem được dòng thời gian thu chi?</span>
                    <div class="grid gap-2">
                        <label v-for="opt in [
                            { value: 'public', icon: Globe, title: 'Công khai', text: 'Ai có link đều xem được, không cần đăng nhập.' },
                            { value: 'private', icon: Lock, title: 'Riêng tư', text: 'Chỉ người có tài khoản trong nhóm mới xem được.' },
                        ]" :key="opt.value" class="card flex cursor-pointer gap-3 p-3" :class="{ 'border-primary ring-2 ring-primary/25': form.visibility === opt.value }">
                            <input v-model="form.visibility" type="radio" :value="opt.value" class="sr-only" />
                            <component :is="opt.icon" class="mt-0.5 size-5 shrink-0 text-primary" />
                            <span><span class="block font-medium">{{ opt.title }}</span><span class="text-sm text-muted-foreground">{{ opt.text }}</span></span>
                        </label>
                    </div>
                </div>

                <FormField label="Số tiền quỹ đang có" hint="Số dư đầu kỳ. Để trống nếu quỹ bắt đầu từ 0." :error="state.errors.opening_balance">
                    <MoneyInput v-model="form.opening_balance" placeholder="0" />
                </FormField>
            </section>

            <input v-model="form.website" type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true" />

            <button class="btn-primary w-full" :disabled="state.busy">{{ state.busy ? 'Đang tạo nhóm...' : 'Tạo nhóm' }}</button>
            <p class="text-center text-sm text-muted-foreground">
                Đã có tài khoản? <RouterLink to="/dang-nhap" class="font-medium text-primary">Đăng nhập</RouterLink> rồi tạo thêm nhóm.
            </p>
        </form>
    </AuthLayout>
</template>
