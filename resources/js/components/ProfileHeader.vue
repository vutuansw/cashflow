<script setup>
import { ShieldCheck, Users } from 'lucide-vue-next';
import CoverArt from './CoverArt.vue';
import UserAvatar from './UserAvatar.vue';

// Phần đầu trang cá nhân, dùng chung cho trang thành viên và /profile (plan/08 mục 2, 10c). Ảnh bìa 3:1 mờ dần
// xuống nền trắng; avatar tròn viền trắng và tên nằm đè lên phần mờ đó, dòng dưới tên là vai trò | nhóm và các
// nút hành động (viên thuốc viền xanh). Chưa có ảnh bìa thì vẽ nền xanh có sóng và cành lá.
// Số đã đóng không nhắc lại ở đây, nằm trong thẻ Giới thiệu.
// Bấm ảnh thì phát sự kiện, trang cha quyết định xem phóng to hay mở menu đổi ảnh.
// Slot "cover"/"avatar": nút máy ảnh; "badge": nhãn sau dòng vai trò; mặc định: các nút hành động.
defineProps({
    member: { type: Object, required: true },
    role: String,
    context: String,
    // Link của tên nhóm (dòng thời gian nhóm), không có thì chỉ hiện chữ.
    contextTo: String,
    interactive: Boolean,
});
const emit = defineEmits(['cover', 'avatar']);
</script>

<template>
    <section class="border-b border-border bg-card">
        <div class="mx-auto max-w-5xl sm:px-4">
            <div class="relative overflow-hidden sm:rounded-b-2xl">
                <!-- Ảnh bìa được cắt đúng 3:1 khi tải lên nên khung cũng 3:1, không bị cắt thêm -->
                <button type="button" class="relative block aspect-[3/1] w-full overflow-hidden bg-cover bg-center" :class="member.cover_url || interactive ? 'cursor-pointer' : 'cursor-default'" :style="member.cover_url ? { backgroundImage: `url(${member.cover_url})` } : {}" :aria-label="member.cover_url ? 'Ảnh bìa' : 'Chưa có ảnh bìa'" @click="emit('cover')">
                    <CoverArt v-if="!member.cover_url" />
                </button>
                <!-- Mờ dần xuống nền trắng để tên đè lên vẫn đọc rõ, kể cả khi ảnh bìa là ảnh chụp -->
                <span class="pointer-events-none absolute inset-x-0 bottom-0 h-3/5 bg-gradient-to-b from-transparent via-card/70 to-card" aria-hidden="true"></span>
                <slot name="cover" />
            </div>

            <div class="relative -mt-12 flex items-center gap-2.5 px-4 pb-4 sm:-mt-16 sm:gap-5 sm:px-6 sm:pb-5">
                <!-- Viền trắng đặc để ảnh bìa không lộ qua avatar chữ cái -->
                <div class="relative shrink-0 rounded-full bg-card p-1 shadow-md">
                    <button type="button" class="block rounded-full" :class="member.avatar_url || interactive ? 'cursor-pointer' : 'cursor-default'" :aria-label="member.avatar_url ? 'Ảnh đại diện' : member.name" @click="emit('avatar')">
                        <UserAvatar :url="member.avatar_url" :name="member.name" class="!size-22 text-4xl sm:!size-36 sm:text-5xl" />
                    </button>
                    <slot name="avatar" />
                </div>

                <!-- Tên, dòng vai trò | nhóm và nút bút đều căn giữa theo chiều dọc với avatar -->
                <div class="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
                    <div class="min-w-0 flex-1">
                        <h1 class="truncate text-[1.375rem] font-bold leading-tight sm:text-[2rem]">{{ member.name }}</h1>
                        <p v-if="role || context || $slots.badge" class="mt-0.5 flex min-w-0 items-center gap-2 text-[13px] sm:mt-1 sm:gap-2.5 sm:text-sm">
                            <span v-if="role" class="inline-flex shrink-0 items-center gap-1"><ShieldCheck class="size-4 text-muted-foreground" /> {{ role }}</span>
                            <span v-if="role && context" class="h-4 w-px shrink-0 bg-border" aria-hidden="true"></span>
                            <span v-if="context" class="inline-flex min-w-0 items-center gap-1"><Users class="size-4 shrink-0 text-muted-foreground" /> <RouterLink v-if="contextTo" :to="contextTo" class="truncate hover:text-primary hover:underline">{{ context }}</RouterLink><span v-else class="truncate">{{ context }}</span></span>
                            <slot name="badge" />
                        </p>
                    </div>
                    <div v-if="$slots.default" class="flex shrink-0 gap-2">
                        <slot />
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
