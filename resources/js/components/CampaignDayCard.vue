<script setup>
import { computed, ref } from 'vue';
import CategoryIcon from './CategoryIcon.vue';
import { formatMoney } from '../lib/money.js';
import { relativeDay } from '../lib/time.js';

// Các khoản đóng quỹ cùng chiến dịch trong một ngày, gộp làm một thẻ (plan/03 mục 4). Bấm để xem danh sách.
const props = defineProps({ post: Object, code: String });
const open = ref(false);
const names = computed(() => {
    const list = props.post.payers.map((p) => p.name);
    return list.length <= 3 ? list.join(', ') : `${list.slice(0, 3).join(', ')} và ${list.length - 3} người khác`;
});
</script>

<template>
    <article class="card p-4">
        <div class="flex items-start gap-3">
            <CategoryIcon :name="post.category?.icon" :color="post.category?.color" />
            <div class="min-w-0 flex-1">
                <h3 class="font-semibold leading-snug">
                    <RouterLink v-if="post.campaign" :to="`/${code}/chien-dich/${post.campaign.slug}`" class="hover:underline">{{ post.campaign.name }}</RouterLink>
                    <template v-else>{{ post.category?.name }}</template>
                </h3>
                <p class="text-xs text-muted-foreground">{{ relativeDay(post.occurred_at) }} · {{ post.count }} người đóng</p>
            </div>
            <p class="shrink-0 font-semibold tabular-nums text-income">+{{ formatMoney(post.total).replace(' đ', '') }}</p>
        </div>
        <button type="button" class="mt-2 min-h-11 w-full rounded-xl text-left text-sm text-muted-foreground" :aria-expanded="open" @click="open = !open">
            {{ names }} <span class="font-medium text-primary">{{ open ? 'Thu gọn' : 'Xem' }}</span>
        </button>
        <ul v-if="open" class="mt-1 divide-y divide-border rounded-xl bg-muted/60 text-sm">
            <li v-for="p in post.payers" :key="p.id" class="flex items-center justify-between gap-3 px-3 py-2">
                <span class="truncate">{{ p.name }}</span>
                <span class="shrink-0 tabular-nums text-muted-foreground">{{ p.time }} · {{ formatMoney(p.amount) }}</span>
            </li>
        </ul>
    </article>
</template>
