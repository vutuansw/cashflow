<script setup>
import { ref } from 'vue';
import { Heart, MessageCircle } from 'lucide-vue-next';
import { api } from '../lib/api.js';
import { toast } from '../lib/notice.js';

// Hàng nút tim và bình luận dưới một bài, dùng chung cho phiếu (PostCard) và bài đăng thành viên (StatusCard).
// Slot mặc định: nút thêm ở cuối hàng (vd "Sửa" của QTV trên phiếu). Xem plan/03 mục 5, plan/21 mục 7.
const props = defineProps({ post: Object, code: String, viewer: Object });
const emit = defineEmits(['login', 'compose']);
const busy = ref(false);

/** Phiếu: /posts/{id}; bài đăng: /statuses/{id}. */
const base = () => `/g/${props.code}/${props.post.kind === 'status' ? 'statuses' : 'posts'}/${props.post.id}`;

/** Nút "Bình luận": thành viên thì mở ô viết ngay dưới bài; người ngoài được mời đăng nhập. */
function comment() {
    if (props.viewer?.can_interact) return emit('compose');
    if (!props.viewer?.logged_in) return emit('login');
    toast('Chỉ thành viên của nhóm mới bình luận được.');
}

async function heart() {
    if (!props.viewer?.can_interact) return emit('login');
    if (busy.value) return;
    busy.value = true;
    const before = { hearted: props.post.hearted, count: props.post.hearts_count };
    props.post.hearted = !before.hearted;
    props.post.hearts_count += props.post.hearted ? 1 : -1;
    try {
        const r = await api(`${base()}/heart`, { method: 'POST' });
        props.post.hearted = r.hearted;
        props.post.hearts_count = r.hearts_count;
    } catch {
        props.post.hearted = before.hearted;
        props.post.hearts_count = before.count;
        toast('Chưa thả tim được, bạn thử lại nhé.', 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="-mx-2 -mb-2 mt-1 flex items-center gap-1 text-sm text-muted-foreground">
        <button type="button" class="inline-flex min-h-10 items-center gap-1.5 rounded-xl px-2 hover:bg-muted" :class="{ 'text-expense': post.hearted }" :aria-pressed="post.hearted" @click="heart">
            <Heart class="size-5" :fill="post.hearted ? 'currentColor' : 'none'" /> {{ post.hearts_count || '' }} <span class="sr-only">tim</span>
        </button>
        <button type="button" class="inline-flex min-h-10 items-center gap-1.5 rounded-xl px-2 hover:bg-muted" @click="comment">
            <MessageCircle class="size-5" /> {{ post.comments_count ? `${post.comments_count} bình luận` : 'Bình luận' }}
        </button>
        <slot />
    </div>
</template>
