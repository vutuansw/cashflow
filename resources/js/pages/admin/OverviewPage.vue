<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { BookUser, CheckCircle2, Circle, Landmark, ArrowLeftRight } from 'lucide-vue-next';
import AdminShell from '../../layouts/AdminShell.vue';
import { adminApi } from '../../lib/api.js';

// Tổng quan nhóm. Ở mốc này mới có phần "Bắt đầu nhanh"; số dư, biểu đồ làm ở M2 (plan/03 mục 6).
const route = useRoute();
const code = computed(() => route.params.group);
const contacts = ref(null);
const settings = ref(null);

onMounted(async () => {
    [contacts.value, settings.value] = await Promise.all([
        adminApi(code.value, '/contacts?status=all').catch(() => []),
        adminApi(code.value, '/settings').catch(() => null),
    ]);
});

const steps = computed(() => [
    { done: (contacts.value?.length ?? 0) > 1, title: 'Thêm danh bạ thành viên', text: 'Dán danh sách từ Excel, Zalo cho nhanh.', to: `/${code.value}/quan-tri/danh-ba`, icon: BookUser },
    { done: Boolean(settings.value?.bank_account_no), title: 'Thêm tài khoản ngân hàng nhận quỹ', text: 'Để tạo mã QR đóng quỹ.', to: `/${code.value}/quan-tri/cai-dat`, icon: Landmark },
    { done: false, title: 'Tạo phiếu thu chi đầu tiên', text: 'Có ở bản cập nhật tới.', to: null, icon: ArrowLeftRight },
]);
</script>

<template>
    <AdminShell title="Tổng quan">
        <div v-if="route.query.moi" class="card mb-4 border-primary/40 bg-primary/5 p-4">
            <p class="font-semibold">Nhóm đã được tạo!</p>
            <p class="mt-1 text-sm text-muted-foreground">Link nhóm: quynhom.hipotech.vn/{{ code }}. Làm 3 bước dưới đây để bắt đầu.</p>
        </div>

        <section class="card p-4">
            <h2 class="font-semibold">Bắt đầu nhanh</h2>
            <ol class="mt-3 space-y-2">
                <li v-for="step in steps" :key="step.title">
                    <component :is="step.to ? 'RouterLink' : 'div'" :to="step.to" class="flex items-start gap-3 rounded-xl p-2" :class="{ 'hover:bg-muted': step.to }">
                        <CheckCircle2 v-if="step.done" class="mt-0.5 size-5 shrink-0 text-income" />
                        <Circle v-else class="mt-0.5 size-5 shrink-0 text-muted-foreground" />
                        <span>
                            <span class="block font-medium" :class="{ 'text-muted-foreground line-through': step.done }">{{ step.title }}</span>
                            <span class="text-sm text-muted-foreground">{{ step.text }}</span>
                        </span>
                    </component>
                </li>
            </ol>
        </section>
    </AdminShell>
</template>
