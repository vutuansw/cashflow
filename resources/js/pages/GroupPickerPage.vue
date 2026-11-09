<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ChevronRight, LogOut, Plus, UserRound, Lock, Globe } from 'lucide-vue-next';
import BrandMark from '../components/BrandMark.vue';
import FormField from '../components/FormField.vue';
import MoneyInput from '../components/MoneyInput.vue';
import Sheet from '../components/Sheet.vue';
import { api } from '../lib/api.js';
import { ignore, useForm } from '../lib/form.js';
import { loadMe, logout, session } from '../lib/session.js';

const router = useRouter();
const creating = ref(false);
const form = reactive({ name: '', code: '', visibility: 'public', opening_balance: null });
const { state, submit } = useForm();

function link(group) {
    return ['owner', 'admin'].includes(group.role) ? `/${group.code}/quan-tri` : `/${group.code}`;
}

async function suggestCode() {
    if (form.code || form.name.trim().length < 2) return;
    const res = await api(`/group-code?${new URLSearchParams({ name: form.name })}`).catch(() => null);
    if (res?.suggestion) form.code = res.suggestion;
}

async function create() {
    const res = await submit(() => api('/groups', { method: 'POST', body: form })).catch(ignore);
    if (!res) return;
    await loadMe(true);
    router.push(`/${res.code}/quan-tri?moi=1`);
}

async function signOut() {
    await logout();
    router.replace('/dang-nhap');
}
</script>

<template>
    <main class="mx-auto min-h-dvh max-w-md px-4 py-6">
        <div class="flex items-center justify-between">
            <BrandMark />
            <div class="flex">
                <RouterLink to="/ca-nhan" class="icon-btn" aria-label="Cá nhân"><UserRound class="size-5" /></RouterLink>
                <button class="icon-btn" aria-label="Đăng xuất" @click="signOut"><LogOut class="size-5" /></button>
            </div>
        </div>

        <h1 class="mt-8 text-2xl font-semibold tracking-tight">Chào {{ session.me?.name }}</h1>
        <p class="mt-1 text-muted-foreground">Chọn nhóm để vào.</p>

        <ul class="mt-6 space-y-3">
            <li v-for="group in session.me?.groups" :key="group.code">
                <RouterLink :to="link(group)" class="card flex items-center gap-3 p-4 hover:bg-muted">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-lg font-semibold text-primary">{{ group.name.charAt(0) }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-semibold">{{ group.name }}</span>
                        <span class="flex items-center gap-1.5 text-sm text-muted-foreground">
                            <component :is="group.visibility === 'private' ? Lock : Globe" class="size-3.5" />
                            {{ group.role_label }}<span v-if="group.locked" class="text-expense"> · Đang tạm khóa</span>
                        </span>
                    </span>
                    <ChevronRight class="size-5 text-muted-foreground" />
                </RouterLink>
            </li>
        </ul>
        <p v-if="!session.me?.groups.length" class="card mt-6 p-4 text-muted-foreground">Bạn chưa thuộc nhóm nào. Tạo nhóm mới hoặc nhờ trưởng nhóm cấp tài khoản.</p>

        <button class="btn-outline mt-6 w-full" @click="creating = true"><Plus class="size-5" /> Tạo nhóm mới</button>

        <Sheet :open="creating" title="Tạo nhóm mới" @close="creating = false">
            <form class="space-y-4" @submit.prevent="create">
                <FormField v-model="form.name" label="Tên nhóm" :error="state.errors.name" required @blur="suggestCode" />
                <FormField v-model="form.code" label="Mã nhóm" hint="Dùng làm link: quynhom.hipotech.vn/ma-nhom" autocapitalize="none" :error="state.errors.code" required />
                <FormField label="Chế độ xem" :error="state.errors.visibility">
                    <select v-model="form.visibility" class="field">
                        <option value="public">Công khai: ai có link đều xem được</option>
                        <option value="private">Riêng tư: chỉ người có tài khoản</option>
                    </select>
                </FormField>
                <FormField label="Số tiền quỹ đang có" :error="state.errors.opening_balance"><MoneyInput v-model="form.opening_balance" placeholder="0" /></FormField>
                <button class="btn-primary w-full" :disabled="state.busy">Tạo nhóm</button>
            </form>
        </Sheet>
    </main>
</template>
