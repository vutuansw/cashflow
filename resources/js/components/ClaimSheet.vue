<script setup>
import { computed, ref, watch } from 'vue';
import { CheckCircle2, ImagePlus, Search, X } from 'lucide-vue-next';
import Sheet from './Sheet.vue';
import MoneyInput from './MoneyInput.vue';
import { api } from '../lib/api.js';
import { useForm, ignore } from '../lib/form.js';
import { previewUrl, shrinkImage } from '../lib/image.js';
import { formatMoney } from '../lib/money.js';
import { fold } from '../lib/text.js';

// Thành viên đã chuyển khoản tự gửi ảnh bill, vào hàng chờ cho thủ quỹ xác nhận. Xem plan/05 mục 5.
// Thành viên đăng nhập được chọn sẵn tên, ảnh bill lên đầu form cho dễ thấy (plan/20).
// Gửi kèm ảnh thì không hỏi số tiền (AI đọc); quên chụp bill thì gõ số tiền thay ảnh (plan/23).
// forOthers: mở từ "Chuyển khoản hộ người khác" khi mình đã đóng đủ (plan/31), không chọn sẵn tên mình.
const props = defineProps({ open: Boolean, code: String, campaign: Object, forOthers: Boolean });
const emit = defineEmits(['close', 'sent']);

const { state, submit } = useForm();
const search = ref('');
const person = ref(null); // { contact_id, name, status, … } hoặc null
const manual = ref(false);
const payerName = ref('');
const amount = ref(null);
const note = ref('');
const bill = ref(null); // { file, url }
const trap = ref(''); // ô bẫy máy gửi tự động
const sent = ref(false);
const fileInput = ref(null);
const noBill = ref(false); // quên chụp bill: gửi số tiền thay cho ảnh

// Chưa đóng, đóng thiếu lên đầu; người đã đủ vẫn chọn được (hỏi lại trước khi gửi).
const rank = { unpaid: 0, partial: 1, paid: 2 };
const people = computed(() =>
    (props.campaign?.people ?? [])
        .filter((p) => p.in_list && p.contact_id && p.status !== 'exempt')
        .sort((a, b) => rank[a.status] - rank[b.status]),
);
const shown = computed(() => {
    const q = fold(search.value);
    return people.value.filter((p) => !q || fold(p.name).includes(q));
});

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        sent.value = false;
        state.errors = {};
        amount.value = props.campaign?.amount_per_member ?? null;
        // Đã đăng nhập và gắn danh bạ: chọn sẵn chính họ, vẫn đổi được để đóng hộ người khác.
        if (props.forOthers) {
            if (isMe.value) person.value = null;
        } else if (!person.value && !manual.value && props.campaign?.me) {
            choose(props.campaign.me);
        }
    },
);

const isMe = computed(() => !!person.value && person.value.contact_id === props.campaign?.me?.contact_id);

function choose(p) {
    person.value = p;
    manual.value = false;
    search.value = '';
    // Đóng thiếu thì điền sẵn phần còn thiếu.
    if (p.status === 'partial' && p.expected) amount.value = p.expected - p.paid;
    else amount.value = props.campaign?.amount_per_member ?? amount.value;
}

function removeBill() {
    if (bill.value) URL.revokeObjectURL(bill.value.url);
    bill.value = null;
}

function reset() {
    person.value = null;
    manual.value = false;
    payerName.value = '';
    note.value = '';
    noBill.value = false;
    removeBill();
}

async function pick(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    const small = await shrinkImage(file, 1600, 0.85);
    if (bill.value) URL.revokeObjectURL(bill.value.url);
    bill.value = { file: small, url: previewUrl(small) };
}

const ready = computed(() => (noBill.value ? !!amount.value : !!bill.value) && (!!person.value || !!payerName.value.trim()));

async function send() {
    if (person.value?.status === 'paid' && !confirm('Bạn đã được ghi nhận đóng đủ. Vẫn gửi bill?')) return;
    if (person.value?.pending_claim && !confirm('Bạn đã gửi bill trước đó, đang chờ xác nhận. Gửi bill mới sẽ thay bill cũ?')) return;

    const body = new FormData();
    if (person.value) body.append('contact_id', person.value.contact_id);
    else body.append('payer_name', payerName.value.trim());
    if (noBill.value) body.append('amount', amount.value ?? '');
    else if (bill.value) body.append('bill', bill.value.file);
    body.append('note', note.value.trim());
    body.append('website', trap.value);

    const done = await submit(() => api(`/g/${props.code}/campaigns/${props.campaign.slug}/claims`, { method: 'POST', body })).catch(ignore);
    if (!done) return;
    sent.value = true;
    reset();
    emit('sent');
}
</script>

<template>
    <Sheet :open="open" title="Gửi bill đã chuyển khoản" @close="emit('close')">
        <div v-if="sent" class="py-4 text-center">
            <CheckCircle2 class="mx-auto size-12 text-income" />
            <p class="mt-3 font-semibold">Đã gửi, thủ quỹ sẽ xác nhận</p>
            <p class="mt-1 text-sm text-muted-foreground">Tên bạn hiện "Chờ xác nhận" tới khi thủ quỹ duyệt bill.</p>
            <button type="button" class="btn-primary mt-4 w-full" @click="emit('close')">Xong</button>
        </div>

        <form v-else class="space-y-4" @submit.prevent="send">
            <!-- Người đóng -->
            <div>
                <div v-if="person" class="flex items-center justify-between gap-2 rounded-xl bg-muted px-3 py-2">
                    <span class="min-w-0">
                        <span class="block text-xs text-muted-foreground">Người đóng</span>
                        <span class="font-semibold">{{ person.name }}</span><span v-if="isMe" class="text-sm text-muted-foreground"> (bạn)</span>
                    </span>
                    <button type="button" class="min-h-9 shrink-0 text-right text-sm font-semibold text-primary" @click="person = null">{{ isMe ? 'Đóng hộ người khác' : 'Đổi' }}</button>
                </div>
                <div v-else-if="manual" class="space-y-1">
                    <p class="text-sm font-medium">Họ tên người đóng <span class="text-expense">*</span></p>
                    <input v-model="payerName" class="field" maxlength="120" placeholder="Họ và tên của bạn" required />
                    <button type="button" class="text-sm font-semibold text-primary" @click="manual = false">Chọn trong danh sách</button>
                </div>
                <template v-else>
                    <p class="mb-1.5 text-sm font-medium">Bạn đóng cho ai? <span class="text-expense">*</span></p>
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input v-model="search" type="search" class="field !pl-9" placeholder="Tìm tên bạn" />
                    </div>
                    <ul class="mt-2 max-h-44 divide-y divide-border overflow-y-auto rounded-xl border border-border">
                        <li v-for="p in shown" :key="p.contact_id">
                            <button type="button" class="flex min-h-11 w-full items-center justify-between gap-2 px-3 text-left hover:bg-muted" @click="choose(p)">
                                <span class="truncate">{{ p.name }}</span>
                                <span v-if="p.pending_claim" class="chip shrink-0 !bg-amber-100 !text-amber-800">Chờ xác nhận</span>
                                <span v-else-if="p.status === 'paid'" class="chip shrink-0">Đã đóng đủ</span>
                                <span v-else-if="p.status === 'partial'" class="shrink-0 text-xs text-amber-700">thiếu {{ formatMoney(p.expected - p.paid) }}</span>
                            </button>
                        </li>
                        <li v-if="!shown.length" class="px-3 py-3 text-center text-sm text-muted-foreground">Không thấy tên này.</li>
                    </ul>
                    <button type="button" class="mt-1 min-h-9 text-sm font-semibold text-primary" @click="manual = true">Không thấy tên tôi</button>
                </template>
                <p v-if="state.errors.payer_name || state.errors.contact_id" class="mt-1 text-sm text-expense">{{ state.errors.payer_name || state.errors.contact_id }}</p>
            </div>

            <!-- Quên chụp bill: số tiền thay cho ảnh, thủ quỹ đối chiếu với sao kê. -->
            <div v-if="noBill">
                <p class="mb-1.5 text-sm font-medium">Số tiền đã chuyển <span class="text-expense">*</span></p>
                <MoneyInput v-model="amount" required :class="{ 'field-error': state.errors.amount }" />
                <p v-if="state.errors.amount" class="mt-1 text-sm text-expense">{{ state.errors.amount }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Không có bill, thủ quỹ sẽ đối chiếu với sao kê. Nên ghi thêm giờ chuyển vào ghi chú.</p>
                <button type="button" class="mt-1 min-h-9 text-sm font-semibold text-primary" @click="noBill = false">Có ảnh bill rồi</button>
            </div>

            <!-- Ảnh bill -->
            <div v-else>
                <p class="mb-1.5 text-sm font-medium">Ảnh bill chuyển khoản <span class="text-expense">*</span></p>
                <div v-if="bill" class="flex items-end gap-3">
                    <div class="relative h-40 w-28 overflow-hidden rounded-xl bg-muted">
                        <img :src="bill.url" alt="Ảnh bill" class="size-full object-cover" />
                        <button type="button" class="absolute right-1 top-1 grid size-7 place-items-center rounded-full bg-black/60 text-white" aria-label="Bỏ ảnh" @click="removeBill"><X class="size-4" /></button>
                    </div>
                    <button type="button" class="btn-outline" @click="fileInput?.click()"><ImagePlus class="size-4" /> Đổi ảnh</button>
                </div>
                <!-- Trông như nút bấm: nhiều người không để ý khung viền nét đứt cũ (plan/20). -->
                <button v-else type="button" class="flex min-h-24 w-full items-center gap-3 rounded-2xl border-2 border-primary bg-primary/10 p-4 text-left hover:bg-primary/15" @click="fileInput?.click()">
                    <span class="grid size-12 shrink-0 place-items-center rounded-full bg-primary text-primary-foreground"><ImagePlus class="size-6" /></span>
                    <span><span class="block text-base font-semibold text-primary">Chọn ảnh bill</span><span class="text-sm text-muted-foreground">Ảnh chụp màn hình chuyển khoản thành công trong app ngân hàng</span></span>
                </button>
                <input ref="fileInput" type="file" accept="image/*" class="sr-only" tabindex="-1" aria-hidden="true" @change="pick" />
                <p v-if="state.errors.bill || state.errors.amount" class="mt-1 text-sm text-expense">{{ state.errors.bill || state.errors.amount }}</p>
                <button v-if="!bill" type="button" class="mt-1 min-h-9 text-sm font-semibold text-primary" @click="noBill = true">Quên chụp bill? Nhập số tiền</button>
            </div>

            <div>
                <p class="mb-1.5 text-sm font-medium">Ghi chú</p>
                <textarea v-model="note" rows="2" maxlength="500" class="field" placeholder="Không bắt buộc, vd đóng hộ bạn Minh"></textarea>
            </div>

            <!-- Ô bẫy: người thật không thấy, máy gửi tự động hay điền vào. -->
            <input v-model="trap" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="absolute -left-[9999px] h-0 w-0 opacity-0" />

            <!-- Mờ tới khi có ảnh bill (hoặc số tiền nếu quên chụp bill), plan/23. -->
            <button type="submit" class="btn-primary w-full" :disabled="state.busy || !ready">{{ state.busy ? 'Đang gửi…' : 'Gửi đi' }}</button>
            <p class="text-center text-xs text-muted-foreground">Bill chỉ thủ quỹ xem được. Khoản này được tính sau khi thủ quỹ xác nhận.</p>
        </form>
    </Sheet>
</template>
