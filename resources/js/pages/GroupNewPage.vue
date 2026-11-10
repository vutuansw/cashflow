<script setup>
import { reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { AlertTriangle, ChevronLeft, Globe, Lock } from 'lucide-vue-next';
import BrandMark from '../components/BrandMark.vue';
import SiteFooter from '../components/SiteFooter.vue';
import FormField from '../components/FormField.vue';
import MoneyInput from '../components/MoneyInput.vue';
import { api } from '../lib/api.js';
import { ignore, useForm } from '../lib/form.js';
import { loadMe, session } from '../lib/session.js';

// Tạo nhóm, xem plan/11. Khách vẫn mở được trang để biết cần chuẩn bị gì,
// nhưng phải tạo tài khoản (hoặc đăng nhập) trước rồi mới tạo được.
const router = useRouter();
const form = reactive({ name: '', code: '', visibility: 'public', opening_balance: null });
const { state, submit } = useForm();
const codeCheck = ref({ available: null, message: null });
const codeTouched = ref(false);
const visibilities = [
    { value: 'public', icon: Globe, title: 'Công khai', text: 'Ai có link đều xem được, không cần đăng nhập.' },
    { value: 'private', icon: Lock, title: 'Riêng tư', text: 'Chỉ người có tài khoản trong nhóm mới xem được.' },
];

let timer;
function checkCode(withSuggestion = false) {
    clearTimeout(timer);
    timer = setTimeout(async () => {
        const params = new URLSearchParams({ code: form.code });
        if (withSuggestion) params.set('name', form.name);
        const res = await api(`/group-code?${params}`).catch(() => null);
        if (!res) return;
        if (withSuggestion && !codeTouched.value && res.suggestion) {
            form.code = res.suggestion;
            codeCheck.value = { available: true, message: null };
        } else {
            codeCheck.value = res;
        }
    }, 350);
}

watch(() => form.name, (name) => { if (!codeTouched.value && name.trim().length >= 2) checkCode(true); });

function onCodeInput(value) {
    codeTouched.value = true;
    form.code = value.toLowerCase().replace(/\s+/g, '-');
    if (form.code.length >= 3) checkCode();
}

async function create() {
    const res = await submit(() => api('/groups', { method: 'POST', body: form })).catch(ignore);
    if (!res) return;
    await loadMe(true);
    router.replace(`/${res.code}/admin?welcome=1`);
}
</script>

<template>
    <main class="mx-auto min-h-dvh max-w-md px-4 py-6">
        <div class="flex items-center gap-1">
            <RouterLink :to="session.me ? '/groups' : '/'" class="icon-btn -ml-2" aria-label="Quay lại"><ChevronLeft class="size-5" /></RouterLink>
            <BrandMark />
        </div>

        <h1 class="mt-6 text-2xl font-semibold tracking-tight">Tạo nhóm</h1>
        <p class="mt-2 text-muted-foreground">Lập quỹ cho nhóm của bạn, bạn sẽ là trưởng nhóm. Miễn phí.</p>

        <div v-if="!session.me" class="mt-6 rounded-xl border border-amber-400/60 bg-amber-50 p-4 dark:bg-amber-950/30">
            <p class="flex items-center gap-2 font-semibold text-amber-800 dark:text-amber-200"><AlertTriangle class="size-5 shrink-0" /> Bạn cần tạo tài khoản trước</p>
            <p class="mt-1 text-sm text-amber-800/80 dark:text-amber-200/80">Có tài khoản rồi bạn quay lại đây để tạo nhóm.</p>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <RouterLink to="/register?next=/groups/new" class="btn-primary">Tạo tài khoản</RouterLink>
                <RouterLink to="/login?next=/groups/new" class="btn-outline">Đăng nhập</RouterLink>
            </div>
        </div>

        <form class="mt-6" @submit.prevent="create">
            <fieldset :disabled="!session.me" class="space-y-4" :class="{ 'opacity-60': !session.me }">
                <FormField v-model="form.name" label="Tên nhóm" placeholder="Chi đoàn A12" :error="state.errors.name" required />
                <FormField label="Mã nhóm" :error="state.errors.code || (codeCheck.available === false ? codeCheck.message : '')" required>
                    <div class="flex items-center rounded-xl border border-border bg-card focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/25">
                        <span class="pl-3 text-sm text-muted-foreground">wepo.hipotech.vn/</span>
                        <input :value="form.code" class="min-h-11 w-full min-w-0 bg-transparent py-2 pr-3 outline-none" autocapitalize="none" spellcheck="false" @input="onCodeInput($event.target.value)" />
                    </div>
                    <span v-if="codeCheck.available && !state.errors.code" class="mt-1 block text-sm text-income">Mã này dùng được.</span>
                </FormField>

                <div>
                    <span class="mb-1.5 block text-sm font-medium">Ai xem được dòng thời gian thu chi?</span>
                    <div class="grid gap-2">
                        <label v-for="opt in visibilities" :key="opt.value" class="card flex cursor-pointer gap-3 p-3" :class="{ 'border-primary ring-2 ring-primary/25': form.visibility === opt.value }">
                            <input v-model="form.visibility" type="radio" :value="opt.value" class="sr-only" />
                            <component :is="opt.icon" class="mt-0.5 size-5 shrink-0 text-primary" />
                            <span><span class="block font-medium">{{ opt.title }}</span><span class="text-sm text-muted-foreground">{{ opt.text }}</span></span>
                        </label>
                    </div>
                </div>

                <FormField label="Số tiền quỹ đang có" hint="Số dư đầu kỳ. Để trống nếu quỹ bắt đầu từ 0." :error="state.errors.opening_balance">
                    <MoneyInput v-model="form.opening_balance" placeholder="0" />
                </FormField>

                <button v-if="session.me" class="btn-primary w-full" :disabled="state.busy">{{ state.busy ? 'Đang tạo nhóm...' : 'Tạo nhóm' }}</button>
            </fieldset>
        </form>
        <SiteFooter />
    </main>
</template>
