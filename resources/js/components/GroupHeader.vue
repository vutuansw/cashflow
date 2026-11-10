<script setup>
import { computed } from 'vue';
import { Globe, Lock } from 'lucide-vue-next';
import CoverArt from './CoverArt.vue';
import UserAvatar from './UserAvatar.vue';

// Phần đầu trang nhóm kiểu nhóm Facebook (plan/08 mục 10d): ảnh bìa rộng bo góc dưới, không có ảnh đại diện nhóm;
// tên nhóm lớn, "Nhóm Công khai · N thành viên", hàng avatar thành viên (bấm ra trang thành viên), các nút bên
// cạnh hàng avatar, thanh tab bên dưới. Slot "account": nút tài khoản ở góc phải ngang tên nhóm; slot mặc định: các nút
// (chia sẻ, quản trị) bên phải hàng avatar; slot "tabs": thanh tab.
const props = defineProps({ group: { type: Object, required: true }, code: String });

// Điện thoại hiện 8 người, máy tính 12; còn lại gộp thành "+N" (N tính theo tổng thành viên trong danh bạ).
const MOBILE = 8;
const DESKTOP = 12;
const people = computed(() => (props.group.members_preview ?? []).slice(0, DESKTOP));
const total = computed(() => Math.max(props.group.members_count ?? 0, props.group.members_preview?.length ?? 0));
const moreMobile = computed(() => Math.max(0, total.value - Math.min(MOBILE, people.value.length)));
const moreDesktop = computed(() => Math.max(0, total.value - people.value.length));
</script>

<template>
    <section class="border-b border-border bg-card">
        <div class="mx-auto max-w-5xl sm:px-4">
            <div class="relative aspect-[3/1] overflow-hidden bg-cover bg-center sm:rounded-b-2xl" :style="group.cover_url ? { backgroundImage: `url(${group.cover_url})` } : {}">
                <CoverArt v-if="!group.cover_url" />
            </div>

            <div class="px-4 pt-3 sm:px-6 sm:pt-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h1 class="text-2xl font-bold leading-tight sm:text-[2rem]">{{ group.name }}</h1>
                        <p class="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground">
                            <component :is="group.visibility === 'public' ? Globe : Lock" class="size-4 shrink-0" />
                            <span>Nhóm {{ group.visibility === 'public' ? 'Công khai' : 'Riêng tư' }}<template v-if="group.members_count"> · <RouterLink :to="`/${code}/thanh-vien`" class="font-semibold text-foreground hover:underline">{{ group.members_count }} thành viên</RouterLink></template></span>
                        </p>
                    </div>
                    <div v-if="$slots.account" class="shrink-0 pt-0.5 sm:pt-1.5">
                        <slot name="account" />
                    </div>
                </div>

                <div v-if="people.length || $slots.default" class="mt-3 flex items-center justify-between gap-3 pb-3">
                    <!-- Hàng avatar thành viên -->
                    <div v-if="people.length" class="flex min-w-0 items-center">
                        <div class="flex -space-x-2">
                            <component :is="p.member_id ? 'RouterLink' : 'span'" v-for="(p, i) in people" :key="i" :to="p.member_id ? `/${code}/thanh-vien/${p.member_id}` : undefined" class="relative rounded-full ring-2 ring-card hover:z-10" :class="{ 'hidden sm:block': i >= MOBILE }" :title="p.name" :aria-label="p.name">
                                <UserAvatar :url="p.avatar_url" :name="p.name" class="!size-8 text-xs sm:!size-9" />
                            </component>
                            <!-- Số người còn lại: vòng tròn cùng cỡ, nằm cuối dãy như Facebook -->
                            <RouterLink v-if="moreMobile" :to="`/${code}/thanh-vien`" class="relative grid size-8 place-items-center rounded-full bg-muted text-xs font-semibold text-muted-foreground ring-2 ring-card hover:z-10 hover:text-foreground sm:hidden" aria-label="Xem tất cả thành viên">+{{ moreMobile }}</RouterLink>
                            <RouterLink v-if="moreDesktop" :to="`/${code}/thanh-vien`" class="relative hidden size-9 place-items-center rounded-full bg-muted text-xs font-semibold text-muted-foreground ring-2 ring-card hover:z-10 hover:text-foreground sm:grid" aria-label="Xem tất cả thành viên">+{{ moreDesktop }}</RouterLink>
                        </div>
                    </div>
                    <div v-if="$slots.default" class="ml-auto flex shrink-0 items-center gap-2">
                        <slot />
                    </div>
                </div>

                <nav v-if="$slots.tabs" class="flex gap-1 overflow-x-auto border-t border-border text-sm font-semibold" aria-label="Mục của nhóm">
                    <slot name="tabs" />
                </nav>
            </div>
        </div>
    </section>
</template>
