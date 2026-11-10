<script setup>
import { Copy } from 'lucide-vue-next';
import { copyText } from '../lib/clipboard.js';

// Hiện mật khẩu tạm đúng một lần, kèm lời nhắn để gửi Zalo.
defineProps({ password: String, message: String });
</script>

<template>
    <div class="space-y-3">
        <div v-if="password" class="rounded-xl bg-muted p-4 text-center">
            <p class="text-sm text-muted-foreground">Mật khẩu tạm (chỉ hiện một lần)</p>
            <p class="mt-1 font-mono text-2xl font-semibold tracking-widest">{{ password }}</p>
        </div>
        <p v-else class="text-sm text-muted-foreground">Số điện thoại này đã có tài khoản WePo, họ dùng mật khẩu đang có.</p>
        <pre class="whitespace-pre-wrap rounded-xl border border-border p-3 text-sm">{{ message }}</pre>
        <button type="button" class="btn-primary w-full" @click="copyText(message, 'Đã chép lời nhắn, dán gửi qua Zalo nhé.')"><Copy class="size-4" /> Chép lời nhắn</button>
    </div>
</template>
