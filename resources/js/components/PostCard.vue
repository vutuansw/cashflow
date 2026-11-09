<script setup>
import { ref } from 'vue';
import { Heart, MessageCircle, Pencil } from 'lucide-vue-next';
import CategoryIcon from './CategoryIcon.vue';
import PhotoGrid from './PhotoGrid.vue';
import { api } from '../lib/api.js';
import { formatMoney } from '../lib/money.js';
import { relativeDay } from '../lib/time.js';
import { toast } from '../lib/notice.js';

// Một phiếu trên timeline: loại, tiêu đề, số tiền (xanh thu, đỏ chi), mô tả, ảnh, tim, bình luận. Xem plan/03 mục 4-5.
const props = defineProps({ post: Object, code: String, viewer: Object });
const emit = defineEmits(['comments', 'login']);
const busy = ref(false);
const expanded = ref(false);

async function heart() {
    if (!props.viewer?.can_interact) return emit('login');
    if (busy.value) return;
    busy.value = true;
    const before = { hearted: props.post.hearted, count: props.post.hearts_count };
    props.post.hearted = !before.hearted;
    props.post.hearts_count += props.post.hearted ? 1 : -1;
    try {
        const r = await api(`/g/${props.code}/posts/${props.post.id}/heart`, { method: 'POST' });
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
    <article class="card p-4">
        <div class="flex items-start gap-3">
            <CategoryIcon :name="post.category?.icon" :color="post.category?.color" />
            <div class="min-w-0 flex-1">
                <h3 class="font-semibold leading-snug">{{ post.title }}</h3>
                <p class="text-xs text-muted-foreground">{{ relativeDay(post.occurred_at) }} · {{ post.category?.name }}<template v-if="post.person && !post.title.includes(post.person)"> · {{ post.person }}</template></p>
            </div>
            <p class="shrink-0 font-semibold tabular-nums" :class="post.type === 'income' ? 'text-income' : 'text-expense'">{{ post.type === 'income' ? '+' : '−' }}{{ formatMoney(post.amount).replace(' đ', '') }}</p>
        </div>
        <p v-if="post.description" class="mt-2 whitespace-pre-line text-sm" :class="{ 'line-clamp-4': !expanded }" @click="expanded = true">{{ post.description }}</p>
        <PhotoGrid v-if="post.photos?.length" :photos="post.photos" class="mt-3" />
        <div class="mt-3 flex items-center gap-1 border-t border-border pt-1 text-sm text-muted-foreground">
            <button type="button" class="inline-flex min-h-11 items-center gap-1.5 rounded-xl px-2 hover:bg-muted" :class="{ 'text-expense': post.hearted }" :aria-pressed="post.hearted" @click="heart">
                <Heart class="size-5" :fill="post.hearted ? 'currentColor' : 'none'" /> {{ post.hearts_count || '' }} <span class="sr-only">tim</span>
            </button>
            <button type="button" class="inline-flex min-h-11 items-center gap-1.5 rounded-xl px-2 hover:bg-muted" @click="emit('comments', post)">
                <MessageCircle class="size-5" /> {{ post.comments_count ? `${post.comments_count} bình luận` : 'Bình luận' }}
            </button>
            <RouterLink v-if="viewer?.can_manage" :to="`/${code}/quan-tri/phieu/${post.id}`" class="ml-auto inline-flex min-h-11 items-center gap-1 rounded-xl px-2 hover:bg-muted"><Pencil class="size-4" /> Sửa</RouterLink>
        </div>
    </article>
</template>
