<script setup>
import { computed } from 'vue';
import { CalendarDays, CheckCircle2, CircleDashed, CircleDot, Flag, MinusCircle, PiggyBank, ShieldCheck, Wallet } from 'lucide-vue-next';
import { formatMoney } from '../lib/money.js';

// Thẻ "Giới thiệu" cột trái trang cá nhân, như Facebook: lời giới thiệu, vai trò trong nhóm, ngày tham gia,
// số đã đóng, chiến dịch đang mở. Không bao giờ có SĐT, địa chỉ. Xem plan/08 mục 2, 5.
// Slot "bio-empty": chỗ để chính chủ bấm thêm giới thiệu khi chưa có.
const props = defineProps({ member: Object, stats: Object, groupName: String, code: String });

const STATUS = {
    paid: { icon: CheckCircle2, label: 'Đã đóng', class: 'text-income' },
    partial: { icon: CircleDot, label: 'Đóng thiếu', class: 'text-amber-700' },
    unpaid: { icon: CircleDashed, label: 'Chưa đóng', class: 'text-muted-foreground' },
    exempt: { icon: MinusCircle, label: 'Được miễn', class: 'text-muted-foreground' },
};

const joined = computed(() => {
    if (!props.member?.joined_at) return null;
    const d = new Date(props.member.joined_at);
    return `tháng ${d.getMonth() + 1}/${d.getFullYear()}`;
});
</script>

<template>
    <section class="card p-4">
        <h2 class="text-lg font-bold">Giới thiệu</h2>

        <p v-if="member?.bio" class="mt-3 whitespace-pre-line border-b border-border pb-3 text-center text-sm">{{ member.bio }}</p>
        <slot v-else name="bio-empty" />

        <ul class="mt-3 space-y-3 text-sm">
            <li v-if="member?.role_label" class="flex items-start gap-3">
                <ShieldCheck class="size-5 shrink-0 text-muted-foreground" />
                <span>{{ member.role_label }}<template v-if="groupName"> tại <RouterLink v-if="code" :to="`/${code}`" class="font-semibold hover:text-primary hover:underline">{{ groupName }}</RouterLink><span v-else class="font-semibold">{{ groupName }}</span></template></span>
            </li>
            <li v-if="joined" class="flex items-start gap-3">
                <CalendarDays class="size-5 shrink-0 text-muted-foreground" />
                <span>Tham gia từ {{ joined }}</span>
            </li>
            <template v-if="stats">
                <li class="flex items-start gap-3">
                    <Wallet class="size-5 shrink-0 text-muted-foreground" />
                    <span>Đã đóng năm {{ stats.year }}: <span class="font-semibold tabular-nums text-income">{{ formatMoney(stats.paid_year) }}</span></span>
                </li>
                <li class="flex items-start gap-3">
                    <PiggyBank class="size-5 shrink-0 text-muted-foreground" />
                    <span>Tổng đã đóng: <span class="font-semibold tabular-nums">{{ formatMoney(stats.paid_total) }}</span></span>
                </li>
                <li class="flex items-start gap-3">
                    <Flag class="size-5 shrink-0 text-muted-foreground" />
                    <span>Đã đóng đủ <span class="font-semibold tabular-nums">{{ stats.campaigns_paid }}/{{ stats.campaigns_total }}</span> chiến dịch</span>
                </li>
            </template>
        </ul>

        <template v-if="stats?.open_campaigns?.length">
            <h3 class="mt-4 text-sm font-semibold text-muted-foreground">Chiến dịch đang mở</h3>
            <ul class="mt-1 divide-y divide-border">
                <li v-for="c in stats.open_campaigns" :key="c.slug">
                    <RouterLink :to="`/${code}/campaigns/${c.slug}`" class="-mx-2 flex items-center justify-between gap-3 rounded-lg px-2 py-2 text-sm hover:bg-muted">
                        <span class="min-w-0 truncate">{{ c.name }}</span>
                        <span class="flex shrink-0 items-center gap-1" :class="STATUS[c.status]?.class">
                            <component :is="STATUS[c.status]?.icon" class="size-4" />
                            {{ STATUS[c.status]?.label }}<template v-if="c.status === 'partial'"> {{ formatMoney(c.paid).replace(' đ', '') }}/{{ formatMoney(c.expected) }}</template>
                        </span>
                    </RouterLink>
                </li>
            </ul>
        </template>
    </section>
</template>
