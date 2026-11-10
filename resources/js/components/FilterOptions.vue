<script setup>
// Một nhóm lựa chọn trong bảng lọc (FilterBar): tiêu đề + các nút bo tròn, chọn một.
// options: [{ v, l, dot?: 'income' | 'expense', icon? }]. Giá trị '' là "tất cả".
defineProps({
    title: { type: String, required: true },
    options: { type: Array, required: true },
});
const model = defineModel();
</script>

<template>
    <section>
        <h3 class="mb-2 text-sm font-semibold">{{ title }}</h3>
        <div class="flex flex-wrap gap-2">
            <button v-for="o in options" :key="o.v" type="button" class="filter-chip" :class="{ 'filter-chip-on': String(model ?? '') === String(o.v) }" :aria-pressed="String(model ?? '') === String(o.v)" @click="model = o.v">
                <span v-if="o.dot" class="size-2 rounded-full" :class="o.dot === 'income' ? 'bg-income' : 'bg-expense'"></span>
                <component :is="o.icon" v-if="o.icon" class="size-4" />
                {{ o.l }}
            </button>
        </div>
    </section>
</template>
