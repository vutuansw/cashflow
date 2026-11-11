<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { Check, Settings, X } from 'lucide-vue-next';
import AdminShell from '../../layouts/AdminShell.vue';
import SegmentedControl from '../../components/SegmentedControl.vue';
import Sheet from '../../components/Sheet.vue';
import StatusCard from '../../components/StatusCard.vue';
import { adminApi, api } from '../../lib/api.js';
import { toast } from '../../lib/notice.js';
import { refreshPending } from '../../lib/pending.js';

// Duyệt bài đăng thành viên khi nhóm bật "Chờ quản trị viên duyệt". Hàng chờ xếp cũ trước; bài đã từ chối vẫn duyệt lại
// được. Không gửi email (plan/21 Q2). Xem plan/21 mục 7.
const route = useRoute();
const code = computed(() => route.params.group);
const status = ref(route.query.status === 'rejected' ? 'rejected' : 'pending');
const items = ref(null);
const counts = ref({});
const postMode = ref(null);
const viewer = ref(null);
const busy = ref(null);
const rejecting = ref(null);
const reason = ref('');

const options = computed(() => [
    { v: 'pending', l: 'Chờ duyệt', count: counts.value.pending ?? 0, badge: true },
    { v: 'rejected', l: 'Không duyệt', count: counts.value.rejected ?? 0 },
]);
const modeText = {
    open: 'Nhóm đang để "Đăng luôn": bài mới của thành viên hiện ngay, không vào hàng chờ.',
    off: 'Nhóm đang tắt đăng bài: chỉ Ban quản trị đăng được.',
};

async function load() {
    const r = await adminApi(code.value, `/statuses?status=${status.value}`);
    items.value = r.data;
    counts.value = r.counts;
    postMode.value = r.post_mode;
    refreshPending(code.value);
}

onMounted(async () => {
    // Quyền xem/thả tim/bình luận của QTV trong thẻ, giống trên timeline.
    api(`/g/${code.value}`).then((g) => (viewer.value = g.viewer)).catch(() => {});
    load();
});
watch(status, () => {
    items.value = null;
    load();
});

async function approve(post) {
    busy.value = post.id;
    try {
        await adminApi(code.value, `/statuses/${post.id}/approve`, { method: 'POST' });
        toast(`Đã duyệt bài của ${post.author.name}. Bài đã lên dòng thời gian.`);
        await load();
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = null;
    }
}

function askReject(post) {
    rejecting.value = post;
    reason.value = '';
}

async function reject() {
    const post = rejecting.value;
    busy.value = post.id;
    try {
        await adminApi(code.value, `/statuses/${post.id}/reject`, { method: 'POST', body: { reason: reason.value } });
        rejecting.value = null;
        toast('Đã từ chối. Người đăng thấy bài với nhãn "Không được duyệt".');
        await load();
    } catch (e) {
        toast(Object.values(e.errors ?? {})[0]?.[0] ?? e.message, 'error');
    } finally {
        busy.value = null;
    }
}

function removed() {
    load();
}
</script>

<template>
    <AdminShell title="Duyệt bài đăng">
        <SegmentedControl v-model="status" :options="options" label="Trạng thái bài" />

        <p v-if="postMode && modeText[postMode] && status === 'pending'" class="mt-3 flex items-start gap-2 rounded-xl bg-muted p-3 text-sm text-muted-foreground">
            <span class="flex-1">{{ modeText[postMode] }}</span>
            <RouterLink :to="`/${code}/admin/settings#post-mode`" class="inline-flex shrink-0 items-center gap-1 font-medium text-primary"><Settings class="size-4" /> Đổi</RouterLink>
        </p>

        <div class="mt-4 space-y-3">
            <p v-if="items === null" class="py-8 text-center text-sm text-muted-foreground">Đang tải…</p>
            <p v-else-if="!items.length" class="card p-6 text-center text-sm text-muted-foreground">
                {{ status === 'pending' ? 'Không có bài nào chờ duyệt.' : 'Chưa từ chối bài nào.' }}
            </p>
            <StatusCard v-for="p in items ?? []" :key="p.id" :post="p" :code="code" :viewer="viewer" @removed="removed">
                <div class="mt-3 flex gap-2 border-t border-border pt-3">
                    <button v-if="p.status === 'pending'" type="button" class="btn-outline flex-1 text-expense" :disabled="busy === p.id" @click="askReject(p)"><X class="size-4" /> Từ chối</button>
                    <button type="button" class="btn-primary flex-1" :disabled="busy === p.id" @click="approve(p)"><Check class="size-4" /> {{ p.status === 'pending' ? 'Duyệt' : 'Duyệt lại' }}</button>
                </div>
            </StatusCard>
        </div>

        <Sheet :open="rejecting !== null" title="Từ chối bài đăng" @close="rejecting = null">
            <form class="space-y-3" @submit.prevent="reject">
                <p class="text-sm text-muted-foreground">Bài không lên dòng thời gian. {{ rejecting?.author.name }} vẫn thấy bài trên trang của mình kèm lý do (nếu có) và xóa được.</p>
                <textarea v-model="reason" class="field min-h-20" maxlength="300" rows="3" placeholder="Lý do (không bắt buộc), vd: Bài không liên quan tới nhóm"></textarea>
                <button class="btn-danger w-full" :disabled="busy === rejecting?.id">Từ chối</button>
            </form>
        </Sheet>
    </AdminShell>
</template>
