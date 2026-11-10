<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowDownLeft, ArrowUpRight, Plus, ScanText, UserRound } from 'lucide-vue-next';
import BrandMark from '../components/BrandMark.vue';
import Sheet from '../components/Sheet.vue';
import BillReadingOverlay from '../components/BillReadingOverlay.vue';
import { readBill } from '../lib/billScan.js';
import { toast } from '../lib/notice.js';
import { adminMenu, adminTabs } from '../lib/nav.js';
import { session } from '../lib/session.js';

// Khung trang quản trị nhóm, xem plan/06 mục 2: tab đáy trên điện thoại, sidebar trên máy tính.
defineProps({ title: String });
const route = useRoute();
const router = useRouter();
const code = computed(() => route.params.group);
const group = computed(() => session.me?.groups.find((g) => g.code === code.value));
const menu = computed(() => adminMenu(code.value));
const tabs = computed(() => adminTabs(code.value));

// Nút + giữa thanh tab mở bảng chọn: Đọc bill / Phiếu thu / Phiếu chi (wireframe M2).
const creating = ref(false);
const createOptions = computed(() => [
    { label: 'Đọc bill chuyển khoản', text: 'Chọn ảnh bill trong máy, AI điền sẵn phiếu.', icon: ScanText, bill: true },
    { label: 'Phiếu thu', text: 'Tiền vào quỹ: đóng quỹ, ủng hộ…', icon: ArrowDownLeft, to: `/${code.value}/quan-tri/phieu/moi?loai=thu`, tone: 'text-income' },
    { label: 'Phiếu chi', text: 'Tiền ra: liên hoan, thăm ốm, hiếu hỉ…', icon: ArrowUpRight, to: `/${code.value}/quan-tri/phieu/moi?loai=chi`, tone: 'text-expense' },
]);

// Đọc bill: chọn ảnh ngay trong bảng (cần thao tác chạm của người dùng để mở thư viện ảnh), đọc xong mở form điền sẵn.
async function onBill(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    creating.value = false;
    try {
        const scan = await readBill(code.value, file);
        router.push(`/${code.value}/quan-tri/phieu/moi?scan=${scan.id}`);
    } catch (e) {
        toast(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'Không tải được ảnh bill.', 'error');
    }
}

function active(item) {
    return item.exact ? route.path === item.to : route.path.startsWith(item.to);
}
</script>

<template>
    <div class="min-h-dvh lg:flex">
        <aside class="sticky top-0 hidden h-dvh w-64 shrink-0 flex-col border-r border-border bg-card p-4 lg:flex">
            <BrandMark />
            <RouterLink to="/nhom" class="mt-6 block rounded-xl bg-muted p-3">
                <span class="block truncate font-semibold">{{ group?.name }}</span>
                <span class="text-sm text-muted-foreground">{{ group?.role_label }} · Đổi nhóm</span>
            </RouterLink>
            <button type="button" class="btn-primary mt-4" @click="creating = true"><Plus class="size-5" /> Tạo phiếu</button>
            <nav class="mt-4 space-y-1">
                <RouterLink v-for="item in menu" :key="item.to" :to="item.to" class="flex min-h-11 items-center gap-3 rounded-xl px-3 font-medium hover:bg-muted" :class="{ 'bg-primary/10 text-primary': active(item) }">
                    <component :is="item.icon" class="size-5" /> {{ item.label }}
                </RouterLink>
            </nav>
            <RouterLink to="/ca-nhan" class="mt-auto flex min-h-11 items-center gap-3 rounded-xl px-3 font-medium hover:bg-muted">
                <UserRound class="size-5" /> {{ session.me?.name }}
            </RouterLink>
        </aside>

        <div class="min-w-0 flex-1 pb-24 lg:pb-8">
            <header class="sticky top-0 z-20 flex min-h-14 items-center gap-2 border-b border-border bg-background/90 px-4 backdrop-blur lg:px-8">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs text-muted-foreground lg:hidden">{{ group?.name }}</p>
                    <h1 class="truncate text-lg font-semibold">{{ title }}</h1>
                </div>
                <slot name="actions" />
            </header>
            <main class="mx-auto max-w-4xl px-4 py-4 lg:px-8 lg:py-6"><slot /></main>
        </div>

        <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 border-t border-border bg-card pb-[env(safe-area-inset-bottom)] lg:hidden">
            <template v-for="tab in tabs" :key="tab.to">
                <button v-if="tab.primary" type="button" class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-xs font-medium text-muted-foreground" @click="creating = true">
                    <span class="-mt-5 grid size-12 place-items-center rounded-full bg-primary text-primary-foreground shadow-lg"><component :is="tab.icon" class="size-6" /></span>
                    <span>{{ tab.label }}</span>
                </button>
                <RouterLink v-else :to="tab.to" class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-xs font-medium" :class="active(tab) ? 'text-primary' : 'text-muted-foreground'">
                    <component :is="tab.icon" class="size-5" />
                    <span>{{ tab.label }}</span>
                </RouterLink>
            </template>
        </nav>

        <Sheet :open="creating" title="Tạo phiếu" @close="creating = false">
            <div class="space-y-2">
                <component :is="opt.bill ? 'label' : 'RouterLink'" v-for="opt in createOptions" :key="opt.label" :to="opt.to" class="flex cursor-pointer items-center gap-3 rounded-2xl border border-border p-3 hover:bg-muted" @click="opt.to && (creating = false)">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-muted" :class="opt.bill ? 'text-primary' : opt.tone"><component :is="opt.icon" class="size-6" /></span>
                    <span><span class="block font-semibold">{{ opt.label }}</span><span class="text-sm text-muted-foreground">{{ opt.text }}</span></span>
                    <input v-if="opt.bill" type="file" accept="image/*" class="sr-only" @change="onBill" />
                </component>
            </div>
        </Sheet>
        <BillReadingOverlay />
    </div>
</template>
