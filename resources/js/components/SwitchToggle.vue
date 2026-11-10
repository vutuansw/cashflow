<script setup>
// Công tắc bật/tắt, vùng chạm 44px. Dùng v-model.
defineProps({ modelValue: Boolean, disabled: Boolean, label: String });
defineEmits(['update:modelValue']);
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        :aria-label="label"
        :disabled="disabled"
        class="relative inline-flex min-h-11 shrink-0 items-center disabled:opacity-60"
        @click="$emit('update:modelValue', !modelValue)"
    >
        <span class="h-7 w-12 rounded-full transition" :class="modelValue ? 'bg-primary' : 'bg-border'"></span>
        <span class="absolute size-6 rounded-full bg-white shadow transition-all" :class="modelValue ? 'left-[1.375rem]' : 'left-0.5'"></span>
    </button>
</template>
