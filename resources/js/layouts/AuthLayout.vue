<script setup>
import AppLogo from '../components/AppLogo.vue';
import SiteFooter from '../components/SiteFooter.vue';
defineProps({ title: String, subtitle: String });
</script>

<template>
    <main class="mx-auto flex min-h-dvh max-w-md flex-col px-4 py-6">
        <AppLogo class="mt-4" />
        <h1 class="mt-8 text-center text-2xl font-semibold tracking-tight">{{ title }}</h1>
        <p v-if="subtitle" class="mt-2 text-center text-muted-foreground">{{ subtitle }}</p>
        <div class="mt-6"><slot /></div>
        <SiteFooter class="mt-auto" />
    </main>
</template>
