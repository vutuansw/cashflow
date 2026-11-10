<script setup>
import { useRouter } from 'vue-router';
import { ChevronLeft } from 'lucide-vue-next';

// Nút ‹ quay lại màn cha. Đi tới từ trong app thì lùi lịch sử (giữ bộ lọc, vị trí cuộn);
// mở thẳng từ link (Zalo, email) thì về trang cha "to". Cần cho PWA vì không có nút back của trình duyệt.
const props = defineProps({ to: { type: String, required: true }, label: String });
const router = useRouter();

function back() {
    if (window.history.state?.back) router.back();
    else router.push(props.to);
}
</script>

<template>
    <button type="button" class="icon-btn shrink-0" :aria-label="label ? `Quay lại ${label}` : 'Quay lại'" @click="back"><ChevronLeft class="size-5" /></button>
</template>
