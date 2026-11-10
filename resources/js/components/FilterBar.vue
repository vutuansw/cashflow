<script setup>
import { ref, useSlots } from 'vue';
import { Search, SlidersHorizontal, X } from 'lucide-vue-next';
import Sheet from './Sheet.vue';

// Thanh lọc dùng chung cho các màn danh sách (kiểu app quản lý tài chính):
// ô tìm + nút "Lọc" mở bảng trượt (slot mặc định, thường là các FilterOptions); dưới là các nhãn bộ lọc đang bật,
// bấm ✕ để bỏ từng cái hoặc "Xóa lọc". Không truyền slot thì chỉ có ô tìm.
//   chips: [{ key, label }] — các điều kiện đang bật; emit remove(key), reset().
//   total: số kết quả hiện có, hiện trên nút "Xem N {unit}" của bảng lọc.
defineProps({
    placeholder: { type: String, default: 'Tìm' },
    chips: { type: Array, default: () => [] },
    total: { type: Number, default: null },
    unit: { type: String, default: 'kết quả' },
    title: { type: String, default: 'Lọc' },
});
const emit = defineEmits(['remove', 'reset']);
const q = defineModel('q', { type: String, default: '' });
const open = ref(false);
const slots = useSlots();
</script>

<template>
    <div class="space-y-3">
        <div class="flex gap-2">
            <div class="relative flex-1">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input v-model="q" type="search" class="field pl-9" :placeholder="placeholder" />
            </div>
            <button v-if="slots.default" type="button" class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-xl border border-border bg-card px-3 text-sm font-medium hover:bg-muted" :class="{ '!border-primary text-primary': chips.length }" aria-haspopup="dialog" @click="open = true">
                <SlidersHorizontal class="size-4" /> Lọc
                <span v-if="chips.length" class="grid size-5 place-items-center rounded-full bg-primary text-[11px] font-semibold text-primary-foreground">{{ chips.length }}</span>
            </button>
        </div>

        <slot name="below" />

        <div v-if="chips.length" class="flex flex-wrap items-center gap-2">
            <button v-for="c in chips" :key="c.key" type="button" class="inline-flex min-h-8 items-center gap-1 rounded-full bg-primary/10 pl-3 pr-2 text-sm font-medium text-primary" :aria-label="`Bỏ lọc ${c.label}`" @click="emit('remove', c.key)">{{ c.label }} <X class="size-3.5" /></button>
            <button type="button" class="text-sm font-medium text-muted-foreground hover:text-foreground" @click="emit('reset')">Xóa lọc</button>
        </div>

        <Sheet v-if="slots.default" :open="open" :title="title" @close="open = false">
            <div class="space-y-5">
                <slot />
                <div class="grid grid-cols-2 gap-2 pt-1">
                    <button type="button" class="btn-outline" @click="emit('reset')">Đặt lại</button>
                    <button type="button" class="btn-primary" @click="open = false">{{ total !== null ? `Xem ${total} ${unit}` : `Xem ${unit}` }}</button>
                </div>
            </div>
        </Sheet>
    </div>
</template>
