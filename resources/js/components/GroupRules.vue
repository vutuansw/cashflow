<script setup>
// Box "Nội quy" trên trang nhóm, xem plan/15. Dài quá 6 dòng thì thu gọn, có nút Xem thêm.
// Chưa có nội quy: chỉ trưởng nhóm/QTV thấy box mời viết.
import { BookOpen, Pencil } from 'lucide-vue-next';
import { nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({
    rules: { type: String, default: null },
    code: { type: String, required: true },
    canManage: Boolean,
});

const text = ref(null);
const expanded = ref(false);
const overflowing = ref(false);

async function measure() {
    expanded.value = false;
    await nextTick();
    overflowing.value = !!text.value && text.value.scrollHeight > text.value.clientHeight + 1;
}
onMounted(measure);
watch(() => props.rules, measure);
</script>

<template>
    <section v-if="rules || canManage" class="card p-4">
        <div class="flex items-center justify-between gap-2">
            <h2 class="flex items-center gap-2 font-semibold"><BookOpen class="size-4 text-primary" /> Nội quy</h2>
            <RouterLink v-if="canManage && rules" :to="`/${code}/admin/settings#rules`" class="inline-flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-primary">
                <Pencil class="size-3.5" /> Sửa
            </RouterLink>
        </div>
        <template v-if="rules">
            <p ref="text" class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed" :class="{ 'line-clamp-6': !expanded }">{{ rules }}</p>
            <button v-if="overflowing || expanded" type="button" class="mt-1 text-sm font-semibold text-primary hover:underline" @click="expanded = !expanded">
                {{ expanded ? 'Thu gọn' : 'Xem thêm' }}
            </button>
        </template>
        <template v-else>
            <p class="mt-2 text-sm text-muted-foreground">Chưa có nội quy. Ghi mức đóng, hạn nộp, mức hỗ trợ hiếu hỉ… để cả nhóm cùng nắm.</p>
            <RouterLink :to="`/${code}/admin/settings#rules`" class="btn-outline mt-3 w-full"><Pencil class="size-4" /> Viết nội quy</RouterLink>
        </template>
    </section>
</template>
