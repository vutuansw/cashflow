<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ImagePlus, Plus, X } from 'lucide-vue-next';
import UserAvatar from './UserAvatar.vue';
import { apiUpload } from '../lib/api.js';
import { useForm, ignore } from '../lib/form.js';
import { previewUrl, shrinkImage } from '../lib/image.js';
import { toast } from '../lib/notice.js';
import { session } from '../lib/session.js';

// Ô viết bài thành viên: chữ + tối đa 10 ảnh, không qua phiếu thu chi. Xem plan/21 mục 7.
// - Không có `post`: ô viết bài mới, thu gọn thành dòng "Bạn đang nghĩ gì?", nháp chữ giữ trong localStorage theo nhóm.
// - Có `post`: sửa bài ngay trong thẻ (StatusCard), bỏ được ảnh cũ, thêm ảnh mới.
const props = defineProps({
    code: String,
    viewer: Object,
    post: { type: Object, default: null },
});
const emit = defineEmits(['posted', 'saved', 'cancel']);

const MAX_PHOTOS = 10;
const MAX_BODY = 5000;

const me = computed(() => session.me);
const editing = computed(() => props.post !== null);
const open = ref(editing.value);
const body = ref(props.post?.body ?? readDraft());
const kept = ref(props.post ? [...props.post.photos] : []);
const files = ref([]); // { key, file, url }
const preparing = ref(0);
const progress = ref(null);
const textarea = ref(null);
const picker = ref(null);
const { state, submit } = useForm();

let seq = 0;
const total = computed(() => kept.value.length + files.value.length);
const canSubmit = computed(() => !state.busy && preparing.value === 0 && (body.value.trim() !== '' || total.value > 0));
const photoError = computed(() => Object.entries(state.errors).find(([k]) => k.startsWith('photos'))?.[1]);

// ---- Nháp ----
function draftKey() {
    return `status-draft:${props.code}`;
}
function readDraft() {
    try {
        return localStorage.getItem(draftKey()) ?? '';
    } catch {
        return '';
    }
}
function writeDraft(value) {
    if (editing.value) return;
    try {
        if (value.trim()) localStorage.setItem(draftKey(), value);
        else localStorage.removeItem(draftKey());
    } catch {
        // Trình duyệt chặn lưu trữ: chỉ không giữ được nháp.
    }
}
watch(body, writeDraft);
// Chuyển sang trang của mình ở nhóm khác (cùng component): lấy nháp của nhóm mới.
watch(
    () => props.code,
    () => {
        if (editing.value) return;
        clearPhotos();
        body.value = readDraft();
    },
);

// ---- Mở, thu gọn ----
async function expand(pick = false) {
    open.value = true;
    await nextTick();
    autosize();
    if (pick) picker.value?.click();
    else textarea.value?.focus();
}

function cancel() {
    if (editing.value) return emit('cancel');
    open.value = false;
    clearPhotos();
    state.errors = {};
}

function autosize() {
    const el = textarea.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${Math.min(el.scrollHeight, 480)}px`;
}

// ---- Ảnh ----
async function choose(e) {
    const chosen = [...(e.target.files ?? [])];
    e.target.value = '';
    const room = MAX_PHOTOS - total.value - preparing.value;
    if (chosen.length > room) toast(`Mỗi bài tối đa ${MAX_PHOTOS} ảnh.`, 'error');
    for (const file of chosen.slice(0, Math.max(0, room))) {
        preparing.value++;
        try {
            const small = await shrinkImage(file);
            files.value.push({ key: ++seq, file: small, url: previewUrl(small) });
        } finally {
            preparing.value--;
        }
    }
}

function dropFile(item) {
    URL.revokeObjectURL(item.url);
    files.value = files.value.filter((f) => f.key !== item.key);
}

function dropKept(photo) {
    kept.value = kept.value.filter((p) => p.id !== photo.id);
}

function clearPhotos() {
    files.value.forEach((f) => URL.revokeObjectURL(f.url));
    files.value = [];
}

// ---- Gửi ----
async function send() {
    if (!canSubmit.value) return;
    const form = new FormData();
    form.append('body', body.value);
    files.value.forEach((f) => form.append('photos[]', f.file, f.file.name));
    if (editing.value) {
        props.post.photos.filter((p) => !kept.value.some((k) => k.id === p.id)).forEach((p) => form.append('remove_photo_ids[]', p.id));
    }

    const path = editing.value ? `/g/${props.code}/statuses/${props.post.id}` : `/g/${props.code}/statuses`;
    progress.value = files.value.length ? 0 : null;
    try {
        const card = await submit(() => apiUpload(path, form, { onProgress: files.value.length ? (p) => (progress.value = p) : undefined }));
        clearPhotos();
        if (editing.value) {
            emit('saved', card);
            return;
        }
        body.value = '';
        open.value = false;
        emit('posted', card);
        toast(card.status === 'pending' ? 'Đã gửi. Bài sẽ hiện khi quản trị viên duyệt.' : 'Đã đăng bài.');
    } catch (e) {
        // useForm đã hiện lỗi (theo trường, hoặc toast khi mất mạng); chữ và ảnh giữ nguyên để gửi lại.
        ignore(e);
    } finally {
        progress.value = null;
    }
}

// Sửa bài dài: giãn ô chữ cho vừa nội dung ngay khi mở.
onMounted(() => editing.value && autosize());
onBeforeUnmount(clearPhotos);
</script>

<template>
    <!-- Thu gọn: dòng "Bạn đang nghĩ gì?" như Facebook -->
    <div v-if="!open" class="card flex items-center gap-3 p-3">
        <UserAvatar :url="me?.avatar_url" :name="me?.name" class="!size-10" />
        <button type="button" class="min-h-11 min-w-0 flex-1 truncate rounded-full bg-muted px-4 text-left text-muted-foreground hover:bg-border" @click="expand()">
            {{ body.trim() ? body : 'Bạn đang nghĩ gì?' }}
        </button>
        <button type="button" class="icon-btn shrink-0 text-primary" aria-label="Đăng ảnh" title="Đăng ảnh" @click="expand(true)"><ImagePlus class="size-6" /></button>
    </div>

    <form v-else :class="editing ? 'mt-3' : 'card p-4'" @submit.prevent="send">
        <div v-if="!editing" class="mb-3 flex items-center gap-3">
            <UserAvatar :url="me?.avatar_url" :name="me?.name" class="!size-10" />
            <div class="min-w-0">
                <p class="truncate font-semibold">{{ me?.name }}</p>
            </div>
        </div>

        <textarea
            ref="textarea"
            v-model="body"
            rows="3"
            :maxlength="MAX_BODY"
            class="field resize-none"
            :class="{ 'field-error': state.errors.body }"
            :placeholder="editing ? 'Nội dung bài' : 'Bạn đang nghĩ gì?'"
            :disabled="state.busy"
            aria-label="Nội dung bài"
            @input="autosize"
        ></textarea>
        <p v-if="state.errors.body" class="mt-1 text-sm text-expense">{{ state.errors.body }}</p>
        <p v-else-if="body.length > MAX_BODY - 300" class="mt-1 text-right text-xs text-muted-foreground">{{ body.length }}/{{ MAX_BODY }}</p>

        <!-- Ảnh: ảnh cũ (khi sửa) rồi ảnh mới chọn, mỗi ảnh có nút bỏ; ô "+" để chọn thêm -->
        <div v-if="total || preparing" class="mt-3 grid grid-cols-4 gap-1.5 sm:grid-cols-5">
            <div v-for="p in kept" :key="`k-${p.id}`" class="relative aspect-square overflow-hidden rounded-xl bg-muted">
                <img :src="p.sm" alt="" class="size-full object-cover" />
                <button type="button" class="absolute right-1 top-1 grid size-7 place-items-center rounded-full bg-black/55 text-white hover:bg-black/70" aria-label="Bỏ ảnh này" :disabled="state.busy" @click="dropKept(p)"><X class="size-4" /></button>
            </div>
            <div v-for="f in files" :key="`n-${f.key}`" class="relative aspect-square overflow-hidden rounded-xl bg-muted">
                <img :src="f.url" alt="" class="size-full object-cover" />
                <button type="button" class="absolute right-1 top-1 grid size-7 place-items-center rounded-full bg-black/55 text-white hover:bg-black/70" aria-label="Bỏ ảnh này" :disabled="state.busy" @click="dropFile(f)"><X class="size-4" /></button>
            </div>
            <div v-for="n in preparing" :key="`p-${n}`" class="grid aspect-square animate-pulse place-items-center rounded-xl bg-muted text-xs text-muted-foreground">Đang xử lý…</div>
            <button v-if="total + preparing < MAX_PHOTOS" type="button" class="grid aspect-square place-items-center rounded-xl border border-dashed border-border text-muted-foreground hover:bg-muted" aria-label="Thêm ảnh" :disabled="state.busy" @click="picker?.click()"><Plus class="size-6" /></button>
        </div>
        <p v-if="photoError" class="mt-1 text-sm text-expense">{{ photoError }}</p>

        <div v-if="progress !== null" class="mt-3 h-1.5 overflow-hidden rounded-full bg-muted" role="progressbar" :aria-valuenow="Math.round(progress * 100)" aria-valuemin="0" aria-valuemax="100">
            <div class="h-full rounded-full bg-primary transition-[width]" :style="{ width: `${Math.round(progress * 100)}%` }"></div>
        </div>

        <input ref="picker" type="file" accept="image/*" multiple class="sr-only" tabindex="-1" aria-hidden="true" @change="choose" />

        <div class="mt-3 flex items-center gap-2">
            <button type="button" class="btn-soft" :disabled="state.busy || total + preparing >= MAX_PHOTOS" @click="picker?.click()"><ImagePlus class="size-5 text-primary" /> Ảnh</button>
            <span class="flex-1"></span>
            <button type="button" class="btn-outline !min-h-10 text-sm" :disabled="state.busy" @click="cancel">{{ editing ? 'Hủy' : 'Để sau' }}</button>
            <button type="submit" class="btn-primary !min-h-10 text-sm" :disabled="!canSubmit">
                {{ state.busy ? (editing ? 'Đang lưu…' : 'Đang đăng…') : editing ? 'Lưu' : 'Đăng' }}
            </button>
        </div>
        <p v-if="!editing && viewer?.post_needs_review" class="mt-2 text-xs text-muted-foreground">Bài sẽ hiện sau khi quản trị viên duyệt.</p>
    </form>
</template>
