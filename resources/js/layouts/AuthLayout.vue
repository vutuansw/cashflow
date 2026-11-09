<script setup>
import BrandMark from '../components/BrandMark.vue';
defineProps({ title: String, subtitle: String });
</script>

<template>
    <main class="mx-auto flex min-h-dvh max-w-md flex-col px-4 py-6">
        <BrandMark />
        <h1 class="mt-8 text-2xl font-semibold tracking-tight">{{ title }}</h1>
        <p v-if="subtitle" class="mt-2 text-muted-foreground">{{ subtitle }}</p>
        <div class="mt-6"><slot /></div>
    </main>
</template>
