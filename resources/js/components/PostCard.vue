<script setup>
import { ref } from 'vue';
import { Pencil } from 'lucide-vue-next';
import CategoryIcon from './CategoryIcon.vue';
import MemberName from './MemberName.vue';
import PhotoGrid from './PhotoGrid.vue';
import PostActions from './PostActions.vue';
import PostComments from './PostComments.vue';
import { formatMoney } from '../lib/money.js';
import { relativeDay } from '../lib/time.js';

// Một phiếu trên timeline: loại, tiêu đề, số tiền (xanh thu, đỏ chi), mô tả, ảnh, tim, bình luận. Xem plan/03 mục 4-5.
defineProps({ post: Object, code: String, viewer: Object });
const emit = defineEmits(['login']);
const expanded = ref(false);
const comments = ref(null);
</script>

<template>
    <article class="card p-4">
        <div class="flex items-start gap-3">
            <CategoryIcon :name="post.category?.icon" :color="post.category?.color" />
            <div class="min-w-0 flex-1">
                <h3 class="text-[15px] font-semibold leading-snug">{{ post.title }}</h3>
                <p class="text-xs text-muted-foreground">{{ relativeDay(post.occurred_at) }} · {{ post.category?.name }}<template v-if="post.person && !post.title.includes(post.person)"> · <MemberName :name="post.person" :member-handle="post.person_member_handle" :code="code" /></template></p>
            </div>
            <p class="shrink-0 font-semibold tabular-nums" :class="post.type === 'income' ? 'text-income' : 'text-expense'">{{ post.type === 'income' ? '+' : '−' }}{{ formatMoney(post.amount).replace(' đ', '') }}</p>
        </div>
        <p v-if="post.description" class="mt-2 whitespace-pre-line text-sm" :class="{ 'line-clamp-4': !expanded }" @click="expanded = true">{{ post.description }}</p>
        <PhotoGrid v-if="post.photos?.length" :photos="post.photos" class="mt-3" />
        <PostActions :post="post" :code="code" :viewer="viewer" @login="emit('login')" @compose="comments?.compose()">
            <RouterLink v-if="viewer?.can_manage" :to="`/${code}/admin/transactions/${post.id}`" class="ml-auto inline-flex min-h-10 items-center gap-1 rounded-xl px-2 hover:bg-muted"><Pencil class="size-4" /> Sửa</RouterLink>
        </PostActions>
        <PostComments ref="comments" :post="post" :code="code" :viewer="viewer" />
    </article>
</template>
