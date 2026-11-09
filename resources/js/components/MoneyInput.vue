<script setup>
import { computed } from 'vue';

// Ô số tiền: hiện 1.500.000, trả về số nguyên. Bàn phím số trên điện thoại.
const props = defineProps({ modelValue: [Number, String, null] });
const emit = defineEmits(['update:modelValue']);

const display = computed(() => {
    const n = Number(props.modelValue);
    return props.modelValue === null || props.modelValue === '' || Number.isNaN(n) ? '' : new Intl.NumberFormat('vi-VN').format(n);
});

function onInput(event) {
    const digits = event.target.value.replace(/\D/g, '').slice(0, 13);
    const value = digits === '' ? null : Number(digits);
    event.target.value = value === null ? '' : new Intl.NumberFormat('vi-VN').format(value);
    emit('update:modelValue', value);
}
</script>

<template>
    <div class="relative">
        <input :value="display" inputmode="numeric" autocomplete="off" class="field pr-8" v-bind="$attrs" @input="onInput" />
        <span class="pointer-events-none absolute inset-y-0 right-3 grid place-items-center text-muted-foreground">đ</span>
    </div>
</template>
