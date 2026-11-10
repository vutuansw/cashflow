<script setup>
import UserAvatar from './UserAvatar.vue';

// Phần đầu trang cá nhân kiểu Facebook: ảnh bìa 3:1, avatar tròn đè lên mép dưới, tên, dòng phụ, giới thiệu.
// Dùng chung cho trang thành viên và /ca-nhan (plan/08 mục 2). Bấm ảnh thì phát sự kiện, trang cha quyết định
// xem phóng to hay mở menu đổi ảnh. Slot "cover"/"avatar" để gắn nút máy ảnh, slot mặc định cho nút bên dưới.
defineProps({ member: { type: Object, required: true }, subtitle: String, interactive: Boolean });
const emit = defineEmits(['cover', 'avatar']);
</script>

<template>
    <section>
        <div class="relative">
            <button type="button" class="block h-36 w-full bg-gradient-to-br from-primary/30 to-primary/5 sm:h-48 sm:rounded-b-3xl" :class="member.cover_url || interactive ? 'cursor-pointer' : 'cursor-default'" :style="member.cover_url ? { backgroundImage: `url(${member.cover_url})`, backgroundSize: 'cover', backgroundPosition: 'center' } : {}" :aria-label="member.cover_url ? 'Ảnh bìa' : 'Chưa có ảnh bìa'" @click="emit('cover')"></button>
            <slot name="cover" />
        </div>
        <div class="flex flex-col items-center px-4 text-center">
            <div class="relative -mt-14">
                <button type="button" class="block rounded-full" :class="member.avatar_url || interactive ? 'cursor-pointer' : 'cursor-default'" :aria-label="member.avatar_url ? 'Ảnh đại diện' : member.name" @click="emit('avatar')">
                    <UserAvatar :url="member.avatar_url" :name="member.name" class="!size-28 border-4 border-background text-4xl" />
                </button>
                <slot name="avatar" />
            </div>
            <h1 class="mt-2 text-2xl font-semibold leading-tight">{{ member.name }}</h1>
            <p v-if="subtitle" class="mt-1 text-sm text-muted-foreground">{{ subtitle }}<slot name="badge" /></p>
            <p v-if="member.bio" class="mt-2 whitespace-pre-line text-sm">{{ member.bio }}</p>
            <slot />
        </div>
    </section>
</template>
