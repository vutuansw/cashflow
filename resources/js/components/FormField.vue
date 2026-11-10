<script setup>
// Nhãn + ô nhập + dòng lỗi. Ô nhập đặt trong slot, hoặc dùng v-model với input thường.
defineProps({
    label: String,
    error: String,
    hint: String,
    modelValue: [String, Number],
    type: { type: String, default: 'text' },
    required: Boolean,
    ai: Boolean, // ô do AI đọc bill điền, xem plan/04 mục 1
});
defineEmits(['update:modelValue']);
</script>

<template>
    <label class="block">
        <span class="mb-1.5 block text-sm font-medium">
            {{ label }}<span v-if="required" class="text-expense"> *</span>
            <span v-if="ai" class="ml-1 rounded-md bg-primary/15 px-1.5 py-0.5 text-[10px] font-semibold text-primary">AI đọc</span>
        </span>
        <slot>
            <input
                :type="type"
                :value="modelValue"
                class="field"
                :class="{ 'field-error': error }"
                v-bind="$attrs"
                @input="$emit('update:modelValue', $event.target.value)"
            />
        </slot>
        <span v-if="error" class="mt-1 block text-sm text-expense">{{ error }}</span>
        <span v-else-if="hint" class="mt-1 block text-sm text-muted-foreground">{{ hint }}</span>
    </label>
</template>
