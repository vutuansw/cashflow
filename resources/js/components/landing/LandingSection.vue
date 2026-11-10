<script setup>
// Một section ưu việt của landing page (plan/14): chữ một bên, điện thoại một bên.
// Slot `visual` chứa iPhone và họa tiết; slot mặc định thêm nội dung dưới danh sách tick.
defineProps({
    id: { type: String, required: true },
    number: { type: String, required: true },
    eyebrow: { type: String, required: true },
    title: { type: String, required: true },
    text: { type: String, required: true },
    points: { type: Array, default: () => [] },
    // true: chữ bên trái, ảnh bên phải (màn rộng). Mobile luôn chữ trước ảnh sau.
    reverse: { type: Boolean, default: false },
    // Nền xanh đậm (section 03): chữ trắng ở cả hai chế độ sáng/tối.
    dark: { type: Boolean, default: false },
});
</script>

<template>
    <section :id="id" class="relative scroll-mt-16 overflow-hidden py-20 lg:py-24" :class="dark ? 'bg-[#0a5541] text-white' : ''">
        <slot name="background" />
        <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 lg:grid-cols-2 lg:gap-14">
            <div :class="reverse ? 'lg:order-1' : 'lg:order-2'">
                <p class="landing-num" :class="{ 'opacity-70': dark }" aria-hidden="true">{{ number }}</p>
                <p class="landing-eyebrow mt-2" :class="dark ? 'text-[#bbf7d0]' : 'text-primary'">{{ eyebrow }}</p>
                <h2 class="landing-display mt-3 text-3xl font-bold leading-tight sm:text-4xl">{{ title }}</h2>
                <p class="mt-5 text-lg leading-relaxed" :class="dark ? 'text-white/75' : 'text-muted-foreground'">{{ text }}</p>
                <ul v-if="points.length" class="mt-7 space-y-3 text-[15px]">
                    <li v-for="point in points" :key="point" class="flex gap-3">
                        <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full" :class="dark ? 'bg-white/15 text-[#86efac]' : 'bg-primary/15 text-primary'">
                            <svg viewBox="0 0 24 24" class="size-3" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L20 7" /></svg>
                        </span>
                        <span>{{ point }}</span>
                    </li>
                </ul>
                <slot />
            </div>
            <div class="relative mx-auto flex w-full justify-center" :class="reverse ? 'lg:order-2' : 'lg:order-1'">
                <slot name="visual" />
            </div>
        </div>
    </section>
</template>
