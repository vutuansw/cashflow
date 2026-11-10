<script setup>
import { computed } from 'vue';

// Ô chọn liền khối kiểu iOS cho bộ lọc có ít lựa chọn loại trừ nhau (kỳ, trạng thái, nhóm người...).
// options: [{ v, l, count?, badge? }]. Từ 4 lựa chọn chữ nhỏ lại một chút cho vừa điện thoại.
// count: số người/mục của lựa chọn, hiện thành dòng nhỏ dưới nhãn (để nhãn không bị cắt trên điện thoại);
// badge: true thì count hiện thành chấm đỏ có số cạnh nhãn (việc cần làm), bằng 0 thì ẩn.
// Từ 4 lựa chọn, chấm đỏ nổi ở góc trên nút để nhãn không bị cắt trên điện thoại.
const props = defineProps({
    options: { type: Array, required: true },
    label: { type: String, default: 'Chọn' },
});
const model = defineModel();
const compact = computed(() => props.options.length >= 4);
const stacked = computed(() => props.options.some((o) => !o.badge && o.count !== undefined && o.count !== null));
</script>

<template>
    <div class="grid auto-cols-fr grid-flow-col rounded-xl bg-muted p-1 font-medium" :class="options.length >= 4 ? 'text-[13px] sm:text-sm' : 'text-sm'" role="tablist" :aria-label="label">
        <button v-for="o in options" :key="o.v" type="button" role="tab" :aria-selected="model === o.v" class="relative inline-flex min-w-0 items-center justify-center rounded-lg px-1 transition" :class="[model === o.v ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground', stacked ? 'min-h-11 flex-col leading-tight' : 'min-h-9 gap-1']" @click="model = o.v">
            <span class="max-w-full truncate">{{ o.l }}</span>
            <template v-if="o.badge"><span v-if="o.count" class="rounded-full bg-expense px-1.5 text-[11px] leading-4 font-semibold text-white" :class="compact && 'absolute -right-0.5 -top-1.5 min-w-4 text-center'">{{ o.count }}</span></template>
            <span v-else-if="stacked" class="text-xs font-semibold tabular-nums" :class="model === o.v ? 'text-primary' : 'text-muted-foreground/80'">{{ o.count ?? '·' }}</span>
        </button>
    </div>
</template>
