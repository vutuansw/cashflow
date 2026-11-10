<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { Download } from 'lucide-vue-next';
import AdminShell from '../../layouts/AdminShell.vue';
import { adminApi } from '../../lib/api.js';
import { formatMoney } from '../../lib/money.js';
import { formatDate, formatDateTime } from '../../lib/time.js';

// Báo cáo: sổ quỹ, theo loại, theo tháng, chiến dịch; xuất Excel. Xem plan/03 mục 7.
const route = useRoute();
const code = computed(() => route.params.group);

const types = [
    { v: 'so-quy', l: 'Sổ quỹ' },
    { v: 'theo-loai', l: 'Theo loại' },
    { v: 'theo-thang', l: 'Theo tháng' },
    { v: 'campaign', l: 'Chiến dịch' },
];
const type = ref(route.query.loai ?? 'so-quy');
const pad = (n) => String(n).padStart(2, '0');
const today = new Date();
const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const presets = {
    month: () => [iso(new Date(today.getFullYear(), today.getMonth(), 1)), iso(today)],
    year: () => [`${today.getFullYear()}-01-01`, `${today.getFullYear()}-12-31`],
    lastYear: () => [`${today.getFullYear() - 1}-01-01`, `${today.getFullYear() - 1}-12-31`],
};
const preset = ref('year');
const from = ref(presets.year()[0]);
const to = ref(presets.year()[1]);
const campaigns = ref([]);
const campaignId = ref(null);
const data = ref(null);
const loading = ref(false);
const error = ref('');

watch(preset, (p) => {
    if (presets[p]) [from.value, to.value] = presets[p]();
});

const query = computed(() => {
    const params = new URLSearchParams(type.value === 'campaign' ? { campaign_id: campaignId.value ?? '' } : { from: from.value, to: to.value });
    return params.toString();
});
const excelUrl = computed(() => `/api/g/${code.value}/admin/reports/${type.value}?${query.value}&format=xlsx`);

async function load() {
    if (type.value === 'campaign' && !campaignId.value) {
        data.value = null;
        return;
    }
    loading.value = true;
    error.value = '';
    try {
        data.value = await adminApi(code.value, `/reports/${type.value}?${query.value}`);
    } catch (e) {
        data.value = null;
        error.value = Object.values(e.errors ?? {})[0]?.[0] ?? e.message;
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    campaigns.value = await adminApi(code.value, '/campaigns').catch(() => []);
    campaignId.value = campaigns.value[0]?.id ?? null;
    load();
});
watch([type, query], () => {
    data.value = null;
    load();
});
</script>

<template>
    <AdminShell title="Báo cáo" :crumbs="[{ label: 'Thêm', to: `/${$route.params.group}/quan-tri/them` }]" mobile-only>
        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 text-sm">
            <button v-for="t in types" :key="t.v" type="button" class="chip !min-h-9 shrink-0 whitespace-nowrap !px-3.5 !text-sm" :class="type === t.v ? '!bg-primary !text-primary-foreground' : ''" @click="type = t.v">{{ t.l }}</button>
        </div>

        <section class="card mt-3 space-y-3 p-3">
            <template v-if="type !== 'campaign'">
                <select v-model="preset" class="field">
                    <option value="month">Tháng này</option>
                    <option value="year">Năm nay</option>
                    <option value="lastYear">Năm trước</option>
                    <option value="custom">Tùy chọn</option>
                </select>
                <div v-if="preset === 'custom'" class="grid grid-cols-2 gap-2">
                    <input v-model="from" type="date" class="field" aria-label="Từ ngày" />
                    <input v-model="to" type="date" class="field" aria-label="Đến ngày" />
                </div>
            </template>
            <select v-else v-model="campaignId" class="field" aria-label="Chiến dịch">
                <option v-if="!campaigns.length" :value="null">Chưa có chiến dịch</option>
                <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <a v-if="data" :href="excelUrl" class="btn-outline w-full"><Download class="size-4" /> Xuất Excel</a>
        </section>

        <p v-if="loading" class="py-8 text-center text-sm text-muted-foreground">Đang tải…</p>
        <p v-else-if="error" class="mt-3 rounded-xl bg-expense/10 p-3 text-sm text-expense">{{ error }}</p>

        <!-- Sổ quỹ -->
        <section v-else-if="data && type === 'so-quy'" class="mt-3 space-y-3">
            <div class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                <div class="card p-3"><p class="text-muted-foreground">Đầu kỳ</p><p class="font-semibold tabular-nums">{{ formatMoney(data.opening) }}</p></div>
                <div class="card p-3"><p class="text-muted-foreground">Thu</p><p class="font-semibold tabular-nums text-income">{{ formatMoney(data.income) }}</p></div>
                <div class="card p-3"><p class="text-muted-foreground">Chi</p><p class="font-semibold tabular-nums text-expense">{{ formatMoney(data.expense) }}</p></div>
                <div class="card p-3"><p class="text-muted-foreground">Cuối kỳ</p><p class="font-semibold tabular-nums">{{ formatMoney(data.closing) }}</p></div>
            </div>
            <ul class="card divide-y divide-border text-sm">
                <li v-for="r in data.rows" :key="r.id" class="flex items-center gap-3 px-3 py-2">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ r.title }}</p>
                        <p class="text-xs text-muted-foreground">{{ formatDateTime(r.date) }} · {{ r.category }}</p>
                    </div>
                    <div class="shrink-0 text-right tabular-nums">
                        <p :class="r.income ? 'text-income' : 'text-expense'">{{ r.income ? `+${formatMoney(r.income).replace(' đ', '')}` : `−${formatMoney(r.expense).replace(' đ', '')}` }}</p>
                        <p class="text-xs text-muted-foreground">Tồn {{ formatMoney(r.balance).replace(' đ', '') }}</p>
                    </div>
                </li>
                <li v-if="!data.rows.length" class="p-4 text-center text-muted-foreground">Không có phiếu nào trong kỳ.</li>
            </ul>
        </section>

        <!-- Theo loại -->
        <section v-else-if="data && type === 'theo-loai'" class="mt-3 space-y-3">
            <div v-for="t in [{ k: 'income', l: 'Thu', c: 'text-income' }, { k: 'expense', l: 'Chi', c: 'text-expense' }]" :key="t.k" class="card p-3">
                <p class="flex justify-between font-semibold"><span>{{ t.l }}</span><span class="tabular-nums" :class="t.c">{{ formatMoney(data[t.k].total) }}</span></p>
                <table class="mt-2 w-full text-sm">
                    <caption class="sr-only">{{ t.l }} theo loại</caption>
                    <tbody>
                        <tr v-for="r in data[t.k].rows" :key="r.category" class="border-t border-border">
                            <td class="py-1.5">{{ r.category }} <span class="text-xs text-muted-foreground">({{ r.count }} phiếu)</span></td>
                            <td class="py-1.5 text-right tabular-nums">{{ formatMoney(r.total) }}</td>
                            <td class="w-14 py-1.5 text-right text-muted-foreground">{{ r.percent }}%</td>
                        </tr>
                        <tr v-if="!data[t.k].rows.length"><td class="py-1.5 text-muted-foreground">Không có.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Theo tháng -->
        <section v-else-if="data && type === 'theo-thang'" class="card mt-3 overflow-x-auto p-3">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-muted-foreground"><th class="py-1 font-medium">Tháng</th><th class="py-1 text-right font-medium">Thu</th><th class="py-1 text-right font-medium">Chi</th><th class="py-1 text-right font-medium">Chênh lệch</th></tr></thead>
                <tbody class="tabular-nums">
                    <tr v-for="r in data.rows" :key="r.month" class="border-t border-border">
                        <td class="py-1.5">{{ r.label.replace('Tháng ', 'T') }}</td>
                        <td class="py-1.5 text-right text-income">{{ formatMoney(r.income).replace(' đ', '') }}</td>
                        <td class="py-1.5 text-right text-expense">{{ formatMoney(r.expense).replace(' đ', '') }}</td>
                        <td class="py-1.5 text-right">{{ formatMoney(r.diff).replace(' đ', '') }}</td>
                    </tr>
                    <tr class="border-t-2 border-border font-semibold">
                        <td class="py-1.5">Tổng</td>
                        <td class="py-1.5 text-right">{{ formatMoney(data.income).replace(' đ', '') }}</td>
                        <td class="py-1.5 text-right">{{ formatMoney(data.expense).replace(' đ', '') }}</td>
                        <td class="py-1.5 text-right">{{ formatMoney(data.income - data.expense).replace(' đ', '') }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Chiến dịch -->
        <section v-else-if="data && type === 'campaign'" class="mt-3 space-y-3">
            <div class="card p-3 text-sm">
                <p class="font-semibold">{{ data.campaign.name }}</p>
                <p class="text-muted-foreground">{{ data.members_paid }}/{{ data.members_total }} người đủ · {{ formatMoney(data.collected) }} / {{ formatMoney(data.expected_total) }}</p>
            </div>
            <ul class="card divide-y divide-border text-sm">
                <li v-for="(r, i) in data.rows" :key="i" class="flex items-center gap-3 px-3 py-2">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ r.name }}</p>
                        <p class="text-xs text-muted-foreground">{{ r.status }}<template v-if="r.last_paid_at"> · {{ formatDate(r.last_paid_at) }}</template></p>
                    </div>
                    <div class="shrink-0 text-right tabular-nums">
                        <p>{{ formatMoney(r.paid) }}</p>
                        <p v-if="r.missing" class="text-xs text-expense">thiếu {{ formatMoney(r.missing) }}</p>
                    </div>
                </li>
            </ul>
        </section>
    </AdminShell>
</template>
