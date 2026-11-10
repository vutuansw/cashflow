<script setup>
import { computed, ref } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';

// Nhãn + ô nhập + dòng lỗi. Ô nhập đặt trong slot, hoặc dùng v-model với input thường.
// Ô mật khẩu có nút con mắt để hiện/ẩn mật khẩu đang gõ.
const props = defineProps({
    label: String,
    error: String,
    hint: String,
    modelValue: [String, Number],
    type: { type: String, default: 'text' },
    required: Boolean,
    ai: [Boolean, String], // ô được điền sẵn (AI đọc bill hoặc SePay); chuỗi thì dùng làm nhãn
});
defineEmits(['update:modelValue']);

const isPassword = computed(() => props.type === 'password');
const reveal = ref(false);
const inputType = computed(() => (isPassword.value && reveal.value ? 'text' : props.type));
</script>

<template>
    <label class="block">
        <span class="mb-1.5 block text-sm font-medium">
            {{ label }}<span v-if="required" class="text-expense"> *</span>
            <span v-if="ai" class="ml-1 rounded-md bg-primary/15 px-1.5 py-0.5 text-[10px] font-semibold text-primary">{{ typeof ai === 'string' ? ai : 'AI đọc' }}</span>
        </span>
        <slot>
            <span class="relative block">
                <input
                    :type="inputType"
                    :value="modelValue"
                    class="field"
                    :class="{ 'field-error': error, 'pr-11': isPassword }"
                    v-bind="$attrs"
                    @input="$emit('update:modelValue', $event.target.value)"
                />
                <button
                    v-if="isPassword"
                    type="button"
                    class="absolute inset-y-0 right-0 grid w-11 place-items-center text-muted-foreground hover:text-foreground"
                    :aria-label="reveal ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                    :aria-pressed="reveal"
                    @click.prevent="reveal = !reveal"
                >
                    <component :is="reveal ? EyeOff : Eye" class="size-5" />
                </button>
            </span>
        </slot>
        <span v-if="error" class="mt-1 block text-sm text-expense">{{ error }}</span>
        <span v-else-if="hint" class="mt-1 block text-sm text-muted-foreground">{{ hint }}</span>
    </label>
</template>
