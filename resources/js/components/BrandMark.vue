<script setup>
import { PiggyBank } from 'lucide-vue-next';
</script>

<template>
    <!-- Logo chữ WePo kèm dòng phụ "Quỹ nhóm" cho người mới biết app làm gì (plan/09). -->
    <RouterLink to="/" class="inline-flex items-center gap-2">
        <span class="grid size-9 place-items-center rounded-xl bg-primary text-primary-foreground"><PiggyBank class="size-5" /></span>
        <span class="flex flex-col leading-none">
            <span class="font-semibold tracking-tight">WePo</span>
            <span class="mt-0.5 text-xs text-muted-foreground">Quỹ nhóm</span>
        </span>
    </RouterLink>
</template>
