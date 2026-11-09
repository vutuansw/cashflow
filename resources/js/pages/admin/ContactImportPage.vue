<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { AlertCircle, Trash2 } from 'lucide-vue-next';
import AdminShell from '../../layouts/AdminShell.vue';
import { adminApi } from '../../lib/api.js';
import { ignore, useForm } from '../../lib/form.js';
import { toast } from '../../lib/notice.js';

// Nhập nhanh danh bạ: dán từ Excel/Google Sheets/Zalo, xem trước, sửa dòng lỗi, lưu. Xem plan/02 mục 4.
const route = useRoute();
const router = useRouter();
const code = computed(() => route.params.group);

const text = ref('');
const rows = ref(null);
const { state, submit } = useForm();

const errorCount = computed(() => rows.value?.filter((r) => r.errors.length).length ?? 0);

async function preview() {
    const res = await submit(() => adminApi(code.value, '/contacts/import/preview', { method: 'POST', body: { text: text.value } })).catch(ignore);
    if (res) rows.value = res.rows;
}

async function recheck() {
    const res = await adminApi(code.value, '/contacts/import/preview', {
        method: 'POST',
        body: { text: rows.value.map((r) => [r.full_name, r.phone ?? '', r.birthday ? r.birthday.split('-').reverse().join('/') : '', r.address ?? ''].join('\t')).join('\n') },
    }).catch(() => null);
    if (res) rows.value = res.rows;
}

function removeRow(index) {
    rows.value.splice(index, 1);
}

async function save() {
    try {
        const res = await submit(() => adminApi(code.value, '/contacts/import', { method: 'POST', body: { rows: rows.value } }));
        toast(`Đã thêm ${res.created} người vào danh bạ.`);
        router.push(`/${code.value}/quan-tri/danh-ba`);
    } catch (e) {
        // 422 trả lại các dòng kèm lỗi mới nhất.
        if (e?.status === 422) await recheck();
    }
}
</script>

<template>
    <AdminShell title="Nhập nhanh danh bạ">
        <template v-if="rows === null">
            <div class="card space-y-2 p-4 text-sm">
                <p class="font-medium">Mỗi dòng một người, theo thứ tự cột:</p>
                <p class="font-mono">Họ tên, SĐT, Ngày sinh, Địa chỉ</p>
                <p class="text-muted-foreground">Bôi đen các cột trong Excel hoặc Google Sheets rồi dán vào đây. Gõ tay thì cách các cột bằng dấu phẩy. Chỉ Họ tên là bắt buộc; dòng tiêu đề được tự bỏ qua.</p>
            </div>
            <textarea v-model="text" rows="12" class="field mt-4 font-mono text-sm" placeholder="Nguyễn Văn An, 0988777222, 15/03/1998, Hà Nội&#10;Trần Thị Bình, 0912345678"></textarea>
            <p v-if="state.errors.text" class="mt-1 text-sm text-expense">{{ state.errors.text }}</p>
            <button class="btn-primary mt-4 w-full" :disabled="state.busy || !text.trim()" @click="preview">Xem trước</button>
        </template>

        <template v-else>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p><b>{{ rows.length }}</b> người<span v-if="errorCount" class="text-expense"> · {{ errorCount }} dòng lỗi</span></p>
                <button class="text-sm font-medium text-primary" @click="rows = null">Dán lại</button>
            </div>

            <ul class="mt-3 space-y-2">
                <li v-for="(row, i) in rows" :key="i" class="card p-3" :class="{ 'border-expense': row.errors.length }">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <input v-model="row.full_name" class="field col-span-2 sm:col-span-1" placeholder="Họ tên" />
                        <input v-model="row.phone" class="field" placeholder="SĐT" inputmode="tel" />
                        <input v-model="row.birthday" type="date" class="field" />
                        <input v-model="row.address" class="field col-span-2 sm:col-span-1" placeholder="Địa chỉ" />
                    </div>
                    <div class="mt-2 flex items-start justify-between gap-2">
                        <ul class="text-sm text-expense">
                            <li v-for="err in row.errors" :key="err" class="flex gap-1"><AlertCircle class="mt-0.5 size-4 shrink-0" /> {{ err }}</li>
                        </ul>
                        <button class="icon-btn -m-2 ml-auto shrink-0 text-muted-foreground" aria-label="Bỏ dòng này" @click="removeRow(i)"><Trash2 class="size-4" /></button>
                    </div>
                </li>
            </ul>

            <div class="sticky bottom-16 mt-4 grid gap-2 bg-background py-2 sm:grid-cols-2 lg:bottom-0">
                <button class="btn-outline" @click="recheck">Kiểm tra lại</button>
                <button class="btn-primary" :disabled="state.busy || !rows.length" @click="save">Lưu {{ rows.length }} người</button>
            </div>
        </template>
    </AdminShell>
</template>
