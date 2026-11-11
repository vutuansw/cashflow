<script setup>
// Box "Top đóng góp" trên trang nhóm, dưới box Nội quy. Xem plan/22.
// Người đóng vượt mức chiến dịch đang mở (tùy tâm: trên trung bình), xếp nhiều tiền trước.
// QTV ẩn được từng phiếu thu khỏi top (vd đóng bù năm trước) ngay tại đây.
import { computed, ref } from 'vue';
import { EyeOff, Trophy } from 'lucide-vue-next';
import MemberName from './MemberName.vue';
import Sheet from './Sheet.vue';
import UserAvatar from './UserAvatar.vue';
import { adminApi } from '../lib/api.js';
import { formatMoney } from '../lib/money.js';
import { formatDate } from '../lib/time.js';
import { toast } from '../lib/notice.js';

const props = defineProps({
    top: { type: Object, required: true },
    code: { type: String, required: true },
    canManage: Boolean,
});
const emit = defineEmits(['changed']);

const PREVIEW = 5;
const expanded = ref(false);
const people = computed(() => (expanded.value ? props.top.people : props.top.people.slice(0, PREVIEW)));
// Mỗi dòng một tông tím dùng chung cho vòng avatar, chữ cái đầu, huy hiệu hạng và số tiền.
// Đóng càng nhiều màu càng đậm: người ít nhất trong top nhạt nhất, nhiều nhất đậm nhất (so trên cả danh sách,
// không chỉ 5 người đang hiện). Pha với nền thẻ và màu chữ nên chế độ tối cũng đúng: "đậm" là nổi hơn trên nền.
const range = computed(() => {
    const amounts = props.top.people.map((p) => p.amount);
    return [Math.min(...amounts), Math.max(...amounts)];
});
function tone(amount) {
    const [lo, hi] = range.value;
    const s = hi > lo ? (amount - lo) / (hi - lo) : 1;
    const base = `color-mix(in oklab, var(--top-contributor) ${Math.round(84 + 16 * s)}%, var(--card))`;
    const color = `color-mix(in oklab, ${base}, var(--foreground) ${Math.round(25 * s)}%)`;
    return {
        color,
        avatar: { '--tw-ring-color': color, color, backgroundColor: `color-mix(in oklab, ${color} 16%, var(--card))` },
        badge: { backgroundColor: color, color: 'var(--card)' },
    };
}

// Bảng chọn phiếu: phiếu của một người (để ẩn) hoặc các phiếu đã ẩn (để bỏ ẩn).
const sheet = ref(null); // { title, rows, loading }
const busy = ref(false);

async function hide(person) {
    if (person.transaction_ids.length === 1) {
        if (!confirm(`Ẩn phiếu ${formatMoney(person.amount)} của ${person.name} khỏi Top đóng góp? Phiếu vẫn tính là đã đóng.`)) return;
        await setExcluded(person.transaction_ids[0], true);
        return;
    }
    openSheet(`Phiếu của ${person.name}`, { contact_id: person.contact_id });
}

async function openSheet(title, query) {
    sheet.value = { title, rows: [], loading: true };
    try {
        const params = new URLSearchParams(Object.entries(query).map(([k, v]) => [k, String(v)]));
        sheet.value.rows = await adminApi(props.code, `/campaigns/${props.top.campaign.id}/top-transactions?${params}`);
    } catch (e) {
        toast(e.message, 'error');
        sheet.value = null;
        return;
    }
    sheet.value.loading = false;
}

async function setExcluded(id, exclude, row = null) {
    if (busy.value) return;
    busy.value = true;
    try {
        await adminApi(props.code, `/transactions/${id}/top`, { method: 'PUT', body: { exclude } });
        if (row) row.exclude_from_top = exclude;
        toast(exclude ? 'Đã ẩn phiếu khỏi Top đóng góp.' : 'Đã tính lại phiếu vào Top đóng góp.');
        emit('changed');
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <section class="card p-4">
        <h2 class="flex items-center gap-2 font-semibold"><Trophy class="size-4 text-[var(--top-contributor)]" /> Top đóng góp</h2>
        <p class="mt-0.5 text-xs text-muted-foreground">
            {{ top.campaign.name }} ·
            <template v-if="top.campaign.amount_per_member">mức {{ formatMoney(top.campaign.amount_per_member) }}</template>
            <template v-else>tùy tâm · trung bình {{ formatMoney(top.campaign.average) }}</template>
        </p>

        <ol class="mt-3 space-y-2">
            <li v-for="p in people" :key="`${p.contact_id ?? 'n'}-${p.rank}`" class="flex min-h-12 items-center gap-3">
                <span class="relative shrink-0">
                    <UserAvatar :url="p.avatar_url" :name="p.name" class="!size-10 ring-2 ring-offset-2 ring-offset-card" :style="tone(p.amount).avatar" />
                    <span class="absolute -bottom-1 -right-1.5 grid size-5 place-items-center rounded-full text-[11px] font-bold tabular-nums ring-2 ring-card" :style="tone(p.amount).badge" :aria-label="`Hạng ${p.rank}`">{{ p.rank }}</span>
                </span>
                <MemberName :name="p.name" :member-handle="p.member_handle" :code="code" class="block min-w-0 flex-1 truncate text-sm" />
                <span class="shrink-0 text-sm font-bold tabular-nums" :style="{ color: tone(p.amount).color }">{{ formatMoney(p.amount) }}</span>
                <button v-if="canManage" type="button" class="inline-grid size-8 shrink-0 place-items-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground" :aria-label="`Ẩn ${p.name} khỏi top`" title="Ẩn khỏi top" :disabled="busy" @click="hide(p)">
                    <EyeOff class="size-4" />
                </button>
            </li>
        </ol>
        <button v-if="top.people.length > PREVIEW" type="button" class="mt-2 text-sm font-semibold text-primary hover:underline" @click="expanded = !expanded">
            {{ expanded ? 'Thu gọn' : `Xem tất cả (${top.people.length})` }}
        </button>

        <p v-if="canManage && top.hidden_count" class="mt-3 border-t border-border pt-2 text-xs text-muted-foreground">
            Đã ẩn {{ top.hidden_count }} phiếu khỏi top ·
            <button type="button" class="font-semibold text-primary hover:underline" @click="openSheet('Phiếu đã ẩn khỏi top', { hidden: 1 })">Xem</button>
        </p>

        <Sheet :open="!!sheet" :title="sheet?.title" @close="sheet = null">
            <p v-if="sheet?.loading" class="py-6 text-center text-sm text-muted-foreground">Đang tải…</p>
            <template v-else-if="sheet">
                <p class="mb-2 text-sm text-muted-foreground">Phiếu ẩn khỏi top vẫn tính vào quỹ và vẫn tính là đã đóng.</p>
                <ul class="divide-y divide-border">
                    <li v-for="row in sheet.rows" :key="row.id" class="flex items-center gap-3 py-2">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ row.title }}</p>
                            <p class="text-xs text-muted-foreground">{{ formatDate(row.occurred_at) }}<template v-if="row.person"> · {{ row.person }}</template></p>
                        </div>
                        <span class="shrink-0 text-sm font-semibold tabular-nums">{{ formatMoney(row.amount) }}</span>
                        <button type="button" class="btn-soft shrink-0 !min-h-8 !px-3" :disabled="busy" @click="setExcluded(row.id, !row.exclude_from_top, row)">
                            {{ row.exclude_from_top ? 'Bỏ ẩn' : 'Ẩn' }}
                        </button>
                    </li>
                </ul>
                <p v-if="!sheet.rows.length" class="py-6 text-center text-sm text-muted-foreground">Không có phiếu nào.</p>
            </template>
        </Sheet>
    </section>
</template>
