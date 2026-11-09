<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { ArrowLeftRight, BookUser, CheckCircle2, ChevronRight, Circle, Inbox, Landmark } from 'lucide-vue-next';
import AdminShell from '../../layouts/AdminShell.vue';
import CategoryDonut from '../../components/CategoryDonut.vue';
import CategoryIcon from '../../components/CategoryIcon.vue';
import MonthBars from '../../components/MonthBars.vue';
import { adminApi } from '../../lib/api.js';
import { formatMoney } from '../../lib/money.js';
import { formatDateTime } from '../../lib/time.js';

// Tổng quan nhóm: số dư, thu chi theo tháng, cơ cấu chi, so với cùng kỳ năm trước,
// chiến dịch đang mở, hàng chờ SePay, phiếu mới nhất. Xem plan/03 mục 6.
const route = useRoute();
const code = computed(() => route.params.group);
const data = ref(null);

onMounted(async () => {
    data.value = await adminApi(code.value, '/overview');
});

const s = computed(() => data.value?.summary);
const steps = computed(() => [
    { done: data.value?.setup.has_contacts, title: 'Thêm danh bạ thành viên', text: 'Dán danh sách từ Excel, Zalo cho nhanh.', to: `/${code.value}/quan-tri/danh-ba`, icon: BookUser },
    { done: data.value?.setup.has_bank, title: 'Thêm tài khoản ngân hàng nhận quỹ', text: 'Để tạo mã QR đóng quỹ.', to: `/${code.value}/quan-tri/cai-dat`, icon: Landmark },
    { done: data.value?.setup.has_transactions, title: 'Tạo phiếu thu chi đầu tiên', text: 'Bấm nút + ở giữa thanh dưới.', to: `/${code.value}/quan-tri/phieu/moi?loai=chi`, icon: ArrowLeftRight },
]);
const showSetup = computed(() => data.value && steps.value.some((x) => !x.done));

// So với cùng kỳ năm trước: "▲ 12%", tăng quá 3 lần thì "gấp 23 lần" cho dễ đọc.
function compareText(now, before) {
    if (!before) return null;
    if (now >= before * 3) return `gấp ${Math.round(now / before)} lần cùng kỳ`;
    const pct = Math.round(((now - before) / before) * 100);
    return `${pct >= 0 ? '▲' : '▼'} ${Math.abs(pct)}% so với cùng kỳ`;
}
</script>

<template>
    <AdminShell title="Tổng quan">
        <div v-if="route.query.moi" class="card mb-4 border-primary/40 bg-primary/5 p-4">
            <p class="font-semibold">Nhóm đã được tạo!</p>
            <p class="mt-1 text-sm text-muted-foreground">Link nhóm: quynhom.hipotech.vn/{{ code }}. Làm 3 bước dưới đây để bắt đầu.</p>
        </div>

        <p v-if="!data" class="py-10 text-center text-sm text-muted-foreground">Đang tải…</p>

        <div v-else class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <section v-if="showSetup" class="card p-4 lg:col-span-2">
                <h2 class="font-semibold">Bắt đầu nhanh</h2>
                <ol class="mt-3 space-y-2">
                    <li v-for="step in steps" :key="step.title">
                        <RouterLink :to="step.to" class="flex items-start gap-3 rounded-xl p-2 hover:bg-muted">
                            <CheckCircle2 v-if="step.done" class="mt-0.5 size-5 shrink-0 text-income" />
                            <Circle v-else class="mt-0.5 size-5 shrink-0 text-muted-foreground" />
                            <span>
                                <span class="block font-medium" :class="{ 'text-muted-foreground line-through': step.done }">{{ step.title }}</span>
                                <span class="text-sm text-muted-foreground">{{ step.text }}</span>
                            </span>
                        </RouterLink>
                    </li>
                </ol>
            </section>

            <section class="card p-4 lg:col-span-2">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <p class="text-sm text-muted-foreground">Số dư quỹ</p>
                        <p class="text-3xl font-semibold tabular-nums">{{ formatMoney(s.balance) }}</p>
                    </div>
                    <RouterLink :to="`/${code}/thong-ke`" class="inline-flex min-h-11 items-center text-sm font-medium text-primary">Thống kê <ChevronRight class="size-4" /></RouterLink>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div class="rounded-xl bg-muted p-3">
                        <p class="text-muted-foreground">Thu {{ s.year }}</p>
                        <p class="font-semibold tabular-nums text-income">{{ formatMoney(s.income) }}</p>
                        <p v-if="compareText(data.compare.this_year.income, data.compare.last_year.income)" class="text-xs text-muted-foreground">{{ compareText(data.compare.this_year.income, data.compare.last_year.income) }}</p>
                    </div>
                    <div class="rounded-xl bg-muted p-3">
                        <p class="text-muted-foreground">Chi {{ s.year }}</p>
                        <p class="font-semibold tabular-nums text-expense">{{ formatMoney(s.expense) }}</p>
                        <p v-if="compareText(data.compare.this_year.expense, data.compare.last_year.expense)" class="text-xs text-muted-foreground">{{ compareText(data.compare.this_year.expense, data.compare.last_year.expense) }}</p>
                    </div>
                </div>
                <MonthBars :months="s.months" :label-year="s.year" height="h-28" class="mt-4" />
            </section>

            <RouterLink v-if="data.pending_sepay" :to="`/${code}/quan-tri/cho-duyet`" class="card flex items-center gap-3 border-amber-400/60 p-4 lg:col-span-2">
                <Inbox class="size-6 text-amber-600" />
                <span class="flex-1"><span class="block font-semibold">{{ data.pending_sepay }} giao dịch chờ duyệt</span><span class="text-sm text-muted-foreground">Tiền chuyển khoản vào tài khoản nhóm</span></span>
                <ChevronRight class="size-5 text-muted-foreground" />
            </RouterLink>

            <section v-for="c in data.campaigns" :key="c.id" class="card p-4">
                <p class="text-xs text-muted-foreground">Chiến dịch đang mở</p>
                <p class="font-semibold">{{ c.name }}</p>
                <div v-if="c.members_total" class="mt-2 h-2 rounded-full bg-muted"><div class="h-2 rounded-full bg-primary" :style="{ width: `${Math.min(100, (c.members_paid / c.members_total) * 100)}%` }"></div></div>
                <p class="mt-1 text-sm text-muted-foreground"><template v-if="c.members_total">{{ c.members_paid }}/{{ c.members_total }} người đã đóng đủ · </template>{{ formatMoney(c.collected) }}</p>
            </section>

            <section class="card p-4">
                <h2 class="font-semibold">Chi theo loại · {{ s.year }}</h2>
                <CategoryDonut v-if="s.expense_by_category.length" :items="s.expense_by_category" :total="s.expense" class="mt-3" />
                <p v-else class="mt-2 text-sm text-muted-foreground">Chưa có khoản chi năm nay.</p>
            </section>

            <section class="card p-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Phiếu mới nhất</h2>
                    <RouterLink :to="`/${code}/quan-tri/phieu`" class="text-sm font-medium text-primary">Tất cả</RouterLink>
                </div>
                <ul class="mt-2 divide-y divide-border">
                    <li v-for="t in data.latest" :key="t.id">
                        <RouterLink :to="`/${code}/quan-tri/phieu/${t.id}`" class="flex items-center gap-3 py-2">
                            <CategoryIcon :name="t.category?.icon" :color="t.category?.color" size="size-9" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ t.title }}</span>
                                <span class="block truncate text-xs text-muted-foreground">{{ formatDateTime(t.occurred_at) }}<template v-if="t.created_by"> · {{ t.created_by }}</template></span>
                            </span>
                            <span class="shrink-0 text-sm font-semibold tabular-nums" :class="t.type === 'income' ? 'text-income' : 'text-expense'">{{ t.type === 'income' ? '+' : '−' }}{{ formatMoney(t.amount).replace(' đ', '') }}</span>
                        </RouterLink>
                    </li>
                    <li v-if="!data.latest.length" class="py-4 text-center text-sm text-muted-foreground">Chưa có phiếu nào.</li>
                </ul>
            </section>
        </div>
    </AdminShell>
</template>
