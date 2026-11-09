<script setup>
import { ref, watch } from 'vue';
import { EyeOff, Eye, Pencil, Send, Trash2 } from 'lucide-vue-next';
import Sheet from './Sheet.vue';
import { api } from '../lib/api.js';
import { useForm, ignore } from '../lib/form.js';
import { relativeDay } from '../lib/time.js';
import { toast } from '../lib/notice.js';

// Bình luận của một phiếu. Ai xem được timeline thì đọc được; chỉ tài khoản của nhóm mới viết. Xem plan/03 mục 5.
const props = defineProps({ post: Object, code: String, viewer: Object });
const emit = defineEmits(['close', 'login']);
const comments = ref([]);
const loading = ref(false);
const body = ref('');
const editing = ref(null);
const { state, submit } = useForm();

watch(() => props.post?.id, async (id) => {
    comments.value = [];
    body.value = '';
    editing.value = null;
    if (!id) return;
    loading.value = true;
    try {
        comments.value = await api(`/g/${props.code}/posts/${id}/comments`);
    } finally {
        loading.value = false;
    }
}, { immediate: true });

async function send() {
    if (!body.value.trim()) return;
    await submit(async () => {
        if (editing.value) {
            const updated = await api(`/g/${props.code}/comments/${editing.value.id}`, { method: 'PUT', body: { body: body.value } });
            Object.assign(editing.value, updated);
            editing.value = null;
        } else {
            comments.value.push(await api(`/g/${props.code}/posts/${props.post.id}/comments`, { method: 'POST', body: { body: body.value } }));
            props.post.comments_count += 1;
        }
        body.value = '';
    }).catch(ignore);
}

function edit(c) {
    editing.value = c;
    body.value = c.body;
}

async function remove(c) {
    if (!confirm('Xóa bình luận này?')) return;
    await api(`/g/${props.code}/comments/${c.id}`, { method: 'DELETE' });
    comments.value = comments.value.filter((x) => x.id !== c.id);
    if (!c.hidden) props.post.comments_count -= 1;
}

async function toggleHide(c) {
    const updated = await api(`/g/${props.code}/comments/${c.id}/hide`, { method: 'POST', body: { hidden: !c.hidden } });
    props.post.comments_count += updated.hidden ? -1 : 1;
    Object.assign(c, updated);
    toast(updated.hidden ? 'Đã ẩn bình luận.' : 'Đã hiện lại bình luận.');
}
</script>

<template>
    <Sheet :open="Boolean(post)" :title="post?.title" @close="emit('close')">
        <p v-if="loading" class="py-6 text-center text-sm text-muted-foreground">Đang tải…</p>
        <p v-else-if="!comments.length" class="py-6 text-center text-sm text-muted-foreground">Chưa có bình luận nào.</p>
        <ul v-else class="space-y-3">
            <li v-for="c in comments" :key="c.id" class="flex gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-primary/15 text-sm font-semibold text-primary">{{ c.user.charAt(0) }}</span>
                <div class="min-w-0 flex-1">
                    <div class="rounded-2xl bg-muted px-3 py-2" :class="{ 'opacity-60': c.hidden }">
                        <p class="text-sm font-semibold">{{ c.user }}</p>
                        <p class="whitespace-pre-line text-sm">{{ c.body }}</p>
                    </div>
                    <p class="mt-0.5 flex flex-wrap items-center gap-x-3 px-2 text-xs text-muted-foreground">
                        <span>{{ relativeDay(c.created_at) }}<template v-if="c.edited"> · đã sửa</template></span>
                        <span v-if="c.hidden" class="font-medium text-expense">Đã bị ẩn</span>
                        <button v-if="c.can_edit" type="button" class="inline-flex min-h-8 items-center gap-1" @click="edit(c)"><Pencil class="size-3" /> Sửa</button>
                        <button v-if="c.can_hide" type="button" class="inline-flex min-h-8 items-center gap-1" @click="toggleHide(c)"><component :is="c.hidden ? Eye : EyeOff" class="size-3" /> {{ c.hidden ? 'Hiện' : 'Ẩn' }}</button>
                        <button v-if="c.can_delete" type="button" class="inline-flex min-h-8 items-center gap-1" @click="remove(c)"><Trash2 class="size-3" /> Xóa</button>
                    </p>
                </div>
            </li>
        </ul>

        <form v-if="viewer?.can_interact" class="sticky bottom-0 mt-4 flex items-end gap-2 bg-card pt-2" @submit.prevent="send">
            <div class="flex-1">
                <p v-if="editing" class="mb-1 text-xs text-muted-foreground">Đang sửa bình luận · <button type="button" class="font-medium text-primary" @click="editing = null; body = ''">Hủy</button></p>
                <textarea v-model="body" rows="1" maxlength="1000" class="field resize-none" :class="{ 'field-error': state.errors.body }" placeholder="Viết bình luận…" @keydown.enter.exact.prevent="send"></textarea>
                <p v-if="state.errors.body" class="mt-1 text-sm text-expense">{{ state.errors.body }}</p>
            </div>
            <button type="submit" class="btn-primary !px-3" :disabled="state.busy || !body.trim()" aria-label="Gửi"><Send class="size-5" /></button>
        </form>
        <div v-else class="mt-4 rounded-xl bg-muted p-3 text-center text-sm">
            Chỉ thành viên của nhóm mới bình luận được.
            <button v-if="!viewer?.logged_in" type="button" class="font-semibold text-primary" @click="emit('login')">Đăng nhập</button>
        </div>
    </Sheet>
</template>
