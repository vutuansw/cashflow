<script setup>
import { computed, ref } from 'vue';
import Sheet from './Sheet.vue';
import { toast } from '../lib/notice.js';
import { formatMoney } from '../lib/money.js';

// Lưới app ngân hàng mở bằng deeplink VietQR (plan/05 mục 5b). Từ 11/11/2026 VietQR chỉ mở
// được app, không điền sẵn gì (plan/19), nên chép sẵn nội dung hoặc số tài khoản trước khi mở.
const props = defineProps({ open: Boolean, apps: Array, content: String, accountNo: String, amount: Number });
const emit = defineEmits(['close']);

const KEY = 'wepo:bank-app';
const last = ref(read());

function read() {
    try {
        return localStorage.getItem(KEY);
    } catch {
        return null;
    }
}

// App đã chọn lần trước lên đầu, còn lại giữ thứ tự phổ biến máy chủ trả về.
const sorted = computed(() => {
    const list = [...(props.apps ?? [])];
    const i = list.findIndex((a) => a.id === last.value);
    if (i > 0) list.unshift(...list.splice(i, 1));
    return list;
});

async function openApp(app) {
    try {
        localStorage.setItem(KEY, app.id);
    } catch {
        // Trình duyệt chặn lưu: lần sau không nhớ app, không sao.
    }
    last.value = app.id;

    // Có nội dung thì chép nội dung (để thủ quỹ nhận ra người đóng), khách thì chép số tài khoản.
    const money = props.amount ? `, số tiền ${formatMoney(props.amount)}` : '';
    const copy = props.content
        ? { text: props.content, done: `Đã chép nội dung. Trong app: nhập số TK${money}, dán nội dung.`, fail: `Nhớ ghi nội dung: ${props.content}` }
        : { text: props.accountNo, done: `Đã chép số tài khoản. Dán vào app${money}, nội dung để mặc định có tên bạn.`, fail: `Số tài khoản: ${props.accountNo}` };
    if (copy.text) {
        try {
            await navigator.clipboard.writeText(copy.text);
            toast(copy.done);
        } catch {
            toast(copy.fail);
        }
        // Đợi một chút cho người dùng kịp đọc nhắc trước khi chuyển sang app.
        setTimeout(() => (location.href = app.url), 1000);
        return;
    }
    location.href = app.url;
}
</script>

<template>
    <Sheet :open="open" title="Chọn app ngân hàng" @close="emit('close')">
        <p class="text-sm text-muted-foreground">App mở ra không tự điền sẵn. {{ content ? 'Nội dung' : 'Số tài khoản' }} được chép sẵn để bạn dán. Muốn điền đủ cả số tiền và nội dung: dùng <b>Lưu ảnh QR</b> rồi quét ảnh trong app.</p>
        <ul class="mt-4 grid grid-cols-4 gap-x-2 gap-y-4">
            <li v-for="app in sorted" :key="app.id">
                <a :href="app.url" class="flex flex-col items-center gap-1 text-center" @click.prevent="openApp(app)">
                    <span class="relative">
                        <img v-if="app.logo" :src="app.logo" :alt="''" loading="lazy" class="size-14 rounded-2xl border border-border bg-white object-contain" />
                        <span v-else class="grid size-14 place-items-center rounded-2xl bg-muted text-xs font-semibold uppercase">{{ app.id.slice(0, 3) }}</span>
                        <span v-if="app.id === last" class="absolute -right-1 -top-1 size-3 rounded-full border-2 border-card bg-primary" aria-label="Dùng lần trước"></span>
                    </span>
                    <span class="line-clamp-2 text-xs leading-tight">{{ app.name }}</span>
                </a>
            </li>
        </ul>
    </Sheet>
</template>
