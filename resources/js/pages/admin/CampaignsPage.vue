<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { ChevronRight, Plus } from 'lucide-vue-next';
import AdminShell from '../../layouts/AdminShell.vue';
import { adminApi } from '../../lib/api.js';
import { formatMoney } from '../../lib/money.js';
import { formatDate } from '../../lib/time.js';

// Danh sách chiến dịch thu, xem plan/05 mục 1.
const route = useRoute();
const code = computed(() => route.params.group);
const campaigns = ref(null);
onMounted(async () => (campaigns.value = await adminApi(code.value, '/campaigns')));
const pct = (c) => (c.members_total ? Math.min(100, (c.members_paid / c.members_total) * 100) : 0);
</script>

<template>
    <AdminShell title="Chiến dịch" :crumbs="[{ label: 'Thêm', to: `/${$route.params.group}/admin/more` }]" mobile-only>
        <template #actions>
            <RouterLink :to="`/${code}/admin/campaigns/new`" class="btn-primary !min-h-10 !px-3"><Plus class="size-4" /> Tạo</RouterLink>
        </template>
        <p v-if="!campaigns" class="py-10 text-center text-sm text-muted-foreground">Đang tải…</p>
        <div v-else-if="!campaigns.length" class="card p-6 text-center">
            <p class="font-semibold">Chưa có chiến dịch nào</p>
            <p class="mt-1 text-sm text-muted-foreground">Tạo chiến dịch như "Thu quỹ năm 2026" để có mã QR đóng quỹ và theo dõi ai đã đóng.</p>
            <RouterLink :to="`/${code}/admin/campaigns/new`" class="btn-primary mt-4">Tạo chiến dịch đầu tiên</RouterLink>
        </div>
        <ul v-else class="space-y-3">
            <li v-for="c in campaigns" :key="c.id">
                <RouterLink :to="`/${code}/admin/campaigns/${c.id}`" class="card block p-4 hover:bg-muted/50">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ c.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                <span class="font-mono">{{ c.transfer_code }}</span> · {{ c.amount_per_member ? `${formatMoney(c.amount_per_member)}/người` : 'tùy tâm' }}<template v-if="c.ends_on"> · hạn {{ formatDate(c.ends_on) }}</template>
                            </p>
                        </div>
                        <span class="chip shrink-0" :class="c.status === 'open' ? '!bg-primary/15 !text-primary' : ''">{{ c.status === 'open' ? 'Đang mở' : 'Đã đóng' }}</span>
                    </div>
                    <div v-if="c.members_total" class="mt-3 h-2 rounded-full bg-muted"><div class="h-2 rounded-full bg-primary" :style="{ width: `${pct(c)}%` }"></div></div>
                    <p class="mt-1 flex items-center justify-between text-sm text-muted-foreground">
                        <span><template v-if="c.members_total">{{ c.members_paid }}/{{ c.members_total }} người · </template>{{ formatMoney(c.collected) }}</span>
                        <ChevronRight class="size-4" />
                    </p>
                </RouterLink>
            </li>
        </ul>
    </AdminShell>
</template>
