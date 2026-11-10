<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { Lock } from 'lucide-vue-next';
import CategoryDonut from '../components/CategoryDonut.vue';
import MonthBars from '../components/MonthBars.vue';
import BackButton from '../components/BackButton.vue';
import { api, ApiError } from '../lib/api.js';
import { formatMoney } from '../lib/money.js';

// Thống kê chi tiết của nhóm: thu chi 12 tháng, cơ cấu chi và thu theo loại. Ai xem được timeline thì xem được. Xem plan/03 mục 6.
const route = useRoute();
const code = computed(() => route.params.group);
const year = ref(null);
const data = ref(null);
const blocked = ref(false);
const group = ref(null);

async function load() {
    try {
        data.value = await api(`/g/${code.value}/summary${year.value ? `?year=${year.value}` : ''}`);
        year.value = data.value.year;
    } catch (e) {
        if (e instanceof ApiError && e.status === 403) blocked.value = true;
    }
}

onMounted(async () => {
    group.value = await api(`/g/${code.value}`).catch(() => null);
    load();
});
watch(year, (v, old) => old && v !== old && load());
</script>

<template>
    <div class="mx-auto min-h-dvh max-w-xl pb-10">
        <header class="sticky top-0 z-10 flex min-h-14 items-center gap-2 border-b border-border bg-background/90 px-2 backdrop-blur">
            <BackButton :to="`/${code}`" label="trang nhóm" />
            <div class="min-w-0 flex-1">
                <p class="truncate text-xs text-muted-foreground">{{ group?.name }}</p>
                <h1 class="font-semibold">Thống kê</h1>
            </div>
            <select v-if="data" v-model.number="year" class="field !w-auto" aria-label="Năm">
                <option v-for="y in data.years" :key="y" :value="y">{{ y }}</option>
            </select>
        </header>

        <div v-if="blocked" class="card m-4 p-6 text-center">
            <Lock class="mx-auto size-8 text-muted-foreground" />
            <p class="mt-3 font-semibold">Nhóm riêng tư</p>
            <RouterLink :to="`/dang-nhap?next=/${code}/thong-ke`" class="btn-primary mt-4 w-full">Đăng nhập</RouterLink>
        </div>

        <div v-else-if="data" class="space-y-3 p-4">
            <section class="grid grid-cols-3 gap-2 text-center">
                <div class="card p-3"><p class="text-xs text-muted-foreground">Số dư hiện tại</p><p class="mt-1 font-semibold tabular-nums">{{ formatMoney(data.balance) }}</p></div>
                <div class="card p-3"><p class="text-xs text-muted-foreground">Thu {{ data.year }}</p><p class="mt-1 font-semibold tabular-nums text-income">{{ formatMoney(data.income) }}</p></div>
                <div class="card p-3"><p class="text-xs text-muted-foreground">Chi {{ data.year }}</p><p class="mt-1 font-semibold tabular-nums text-expense">{{ formatMoney(data.expense) }}</p></div>
            </section>

            <section class="card p-4">
                <h2 class="font-semibold">Thu chi theo tháng</h2>
                <MonthBars :months="data.months" :label-year="data.year" height="h-40" class="mt-3" />
            </section>

            <section class="card p-4">
                <h2 class="font-semibold">Chi theo loại</h2>
                <CategoryDonut v-if="data.expense_by_category.length" :items="data.expense_by_category" :total="data.expense" class="mt-3" />
                <p v-else class="mt-2 text-sm text-muted-foreground">Năm {{ data.year }} chưa có khoản chi.</p>
            </section>

            <section class="card p-4">
                <h2 class="font-semibold">Thu theo loại</h2>
                <CategoryDonut v-if="data.income_by_category.length" :items="data.income_by_category" :total="data.income" class="mt-3" />
                <p v-else class="mt-2 text-sm text-muted-foreground">Năm {{ data.year }} chưa có khoản thu.</p>
            </section>

            <p class="text-center text-xs text-muted-foreground">Số dư = số dư đầu kỳ {{ formatMoney(data.opening_balance) }} + tổng thu − tổng chi.</p>
        </div>
    </div>
</template>
