<script setup>
import { onMounted, reactive, ref } from 'vue';
import SystemShell from '../../layouts/SystemShell.vue';
import FormField from '../../components/FormField.vue';
import Sheet from '../../components/Sheet.vue';
import { systemApi, systemSession } from '../../lib/systemApi.js';
import { useForm, ignore } from '../../lib/form.js';
import { copyText } from '../../lib/clipboard.js';
import { formatDateTime } from '../../lib/time.js';
import { toast } from '../../lib/notice.js';

// Thêm/khóa tài khoản quản trị /system (plan/02 mục 6).
const admins = ref([]);
const open = ref(false);
const form = reactive({ name: '', email: '' });
const created = ref(null);
const { state, submit } = useForm();
const load = async () => (admins.value = await systemApi('/admins'));
onMounted(load);

async function add() {
    const res = await submit(() => systemApi('/admins', { method: 'POST', body: form })).catch(ignore);
    if (!res) return;
    created.value = res;
    form.name = form.email = '';
    load();
}
async function toggle(a) {
    const status = a.status === 'active' ? 'locked' : 'active';
    if (status === 'locked' && !confirm(`Khóa ${a.email}? Người này mất quyền ngay.`)) return;
    await systemApi(`/admins/${a.id}`, { method: 'PUT', body: { status } }).catch((e) => toast(e.message, 'error'));
    load();
}
</script>

<template>
    <SystemShell title="Quản trị viên">
        <template #actions><button type="button" class="btn-primary !min-h-10 !px-3" @click="open = true; created = null">Thêm</button></template>
        <ul class="card divide-y divide-border text-sm">
            <li v-for="a in admins" :key="a.id" class="flex items-center gap-3 p-3">
                <div class="min-w-0 flex-1">
                    <p class="font-medium">{{ a.name }} <span class="text-muted-foreground">{{ a.email }}</span></p>
                    <p class="text-xs text-muted-foreground">{{ a.last_login_at ? `Đăng nhập lần cuối ${formatDateTime(a.last_login_at)}` : 'Chưa đăng nhập' }}</p>
                </div>
                <span v-if="a.status === 'locked'" class="chip !bg-expense/15 !text-expense">Đã khóa</span>
                <button v-if="a.id !== systemSession.admin?.id" type="button" class="btn-outline !min-h-9 !px-3 text-sm" @click="toggle(a)">{{ a.status === 'active' ? 'Khóa' : 'Mở khóa' }}</button>
            </li>
        </ul>
        <Sheet :open="open" title="Thêm quản trị viên" @close="open = false">
            <div v-if="created" class="space-y-3">
                <p class="text-sm">Mật khẩu của {{ created.admin.email }}, chỉ hiện một lần:</p>
                <p class="rounded-xl bg-muted p-3 text-center font-mono text-lg">{{ created.temp_password }}</p>
                <button type="button" class="btn-primary w-full" @click="copyText(created.temp_password, 'Đã chép mật khẩu.')">Chép mật khẩu</button>
            </div>
            <form v-else class="space-y-3" @submit.prevent="add">
                <FormField v-model="form.name" label="Họ tên" required :error="state.errors.name" />
                <FormField v-model="form.email" type="email" label="Email" required :error="state.errors.email" />
                <button class="btn-primary w-full" :disabled="state.busy">Thêm</button>
            </form>
        </Sheet>
    </SystemShell>
</template>
