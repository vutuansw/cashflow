<script setup>
import { nextTick, ref } from 'vue';
import { EyeOff, Eye, Pencil, Send, Trash2 } from 'lucide-vue-next';
import MemberName from './MemberName.vue';
import UserAvatar from './UserAvatar.vue';
import { api } from '../lib/api.js';
import { useForm, ignore } from '../lib/form.js';
import { relativeDay } from '../lib/time.js';
import { toast } from '../lib/notice.js';

// Bình luận ngay dưới phiếu: sẵn 5 bình luận mới nhất (đi kèm thẻ timeline), "Xem thêm" tải tiếp bình luận cũ hơn.
// Ai xem được timeline thì đọc được; chỉ tài khoản của nhóm mới viết. Xem plan/03 mục 5.
const props = defineProps({ post: Object, code: String, viewer: Object });
const body = ref('');
const editing = ref(null);
const composing = ref(false);
const loadingMore = ref(false);
const input = ref(null);
const { state, submit } = useForm();

/** Mở ô viết bình luận (nút "Bình luận" trên thẻ gọi vào đây). */
async function compose() {
    composing.value = true;
    await nextTick();
    input.value?.focus();
}

function closeComposer() {
    composing.value = false;
    editing.value = null;
    body.value = '';
}

async function loadMore() {
    const oldest = props.post.comments[0];
    loadingMore.value = true;
    try {
        const r = await api(`/g/${props.code}/posts/${props.post.id}/comments${oldest ? `?before=${oldest.id}` : ''}`);
        props.post.comments.unshift(...r.data);
        props.post.comments_more = r.has_more;
    } catch {
        toast('Chưa tải được bình luận, bạn thử lại nhé.', 'error');
    } finally {
        loadingMore.value = false;
    }
}

async function send() {
    if (!body.value.trim()) return;
    const wasEditing = Boolean(editing.value);
    await submit(async () => {
        if (wasEditing) {
            const updated = await api(`/g/${props.code}/comments/${editing.value.id}`, { method: 'PUT', body: { body: body.value } });
            Object.assign(editing.value, updated);
        } else {
            props.post.comments.push(await api(`/g/${props.code}/posts/${props.post.id}/comments`, { method: 'POST', body: { body: body.value } }));
            props.post.comments_count += 1;
        }
        closeComposer();
        toast(wasEditing ? 'Đã cập nhật bình luận.' : 'Đã gửi bình luận.');
    }).catch(ignore);
}

function edit(c) {
    editing.value = c;
    body.value = c.body;
    compose();
}

async function remove(c) {
    if (!confirm('Xóa bình luận này?')) return;
    await api(`/g/${props.code}/comments/${c.id}`, { method: 'DELETE' });
    props.post.comments = props.post.comments.filter((x) => x.id !== c.id);
    if (!c.hidden) props.post.comments_count -= 1;
    if (editing.value?.id === c.id) closeComposer();
}

async function toggleHide(c) {
    const updated = await api(`/g/${props.code}/comments/${c.id}/hide`, { method: 'POST', body: { hidden: !c.hidden } });
    props.post.comments_count += updated.hidden ? -1 : 1;
    Object.assign(c, updated);
    toast(updated.hidden ? 'Đã ẩn bình luận.' : 'Đã hiện lại bình luận.');
}

defineExpose({ compose });
</script>

<template>
    <div v-if="post.comments?.length || post.comments_more || composing" class="mt-1 space-y-2.5 border-t border-border pt-3">
        <button v-if="post.comments_more" type="button" class="min-h-8 text-sm font-medium text-muted-foreground hover:text-foreground" :disabled="loadingMore" @click="loadMore">
            {{ loadingMore ? 'Đang tải…' : 'Xem thêm bình luận' }}
        </button>

        <ul v-if="post.comments?.length" class="space-y-2.5">
            <li v-for="c in post.comments" :key="c.id" class="flex gap-2">
                <RouterLink v-if="c.user.member_id" :to="`/${code}/members/${c.user.member_id}`" class="shrink-0" :aria-label="`Trang của ${c.user.name}`">
                    <UserAvatar :url="c.user.avatar_url" :name="c.user.name" class="text-xs" />
                </RouterLink>
                <UserAvatar v-else :url="c.user.avatar_url" :name="c.user.name" class="text-xs" />
                <div class="min-w-0 flex-1">
                    <div class="inline-block max-w-full rounded-2xl bg-muted px-3 py-1.5" :class="{ 'opacity-60': c.hidden }">
                        <MemberName :name="c.user.name" :member-id="c.user.member_id" :code="code" class="block text-sm font-semibold" />
                        <p class="whitespace-pre-line break-words text-sm">{{ c.body }}</p>
                    </div>
                    <p class="flex flex-wrap items-center gap-x-3 px-2 text-xs text-muted-foreground">
                        <span>{{ relativeDay(c.created_at) }}<template v-if="c.edited"> · đã sửa</template></span>
                        <span v-if="c.hidden" class="font-medium text-expense">Đã bị ẩn</span>
                        <button v-if="c.can_edit" type="button" class="inline-flex min-h-8 items-center gap-1" @click="edit(c)"><Pencil class="size-3" /> Sửa</button>
                        <button v-if="c.can_hide" type="button" class="inline-flex min-h-8 items-center gap-1" @click="toggleHide(c)"><component :is="c.hidden ? Eye : EyeOff" class="size-3" /> {{ c.hidden ? 'Hiện' : 'Ẩn' }}</button>
                        <button v-if="c.can_delete" type="button" class="inline-flex min-h-8 items-center gap-1" @click="remove(c)"><Trash2 class="size-3" /> Xóa</button>
                    </p>
                </div>
            </li>
        </ul>

        <form v-if="composing && viewer?.can_interact" class="flex items-end gap-2" @submit.prevent="send">
            <div class="flex-1">
                <p v-if="editing" class="mb-1 text-xs text-muted-foreground">Đang sửa bình luận · <button type="button" class="font-medium text-primary" @click="closeComposer">Hủy</button></p>
                <textarea ref="input" v-model="body" rows="1" maxlength="1000" class="field resize-none" :class="{ 'field-error': state.errors.body }" placeholder="Viết bình luận…" @keydown.enter.exact.prevent="send" @keydown.esc="closeComposer"></textarea>
                <p v-if="state.errors.body" class="mt-1 text-sm text-expense">{{ state.errors.body }}</p>
            </div>
            <button type="submit" class="btn-primary !px-3" :disabled="state.busy || !body.trim()" aria-label="Gửi"><Send class="size-5" /></button>
        </form>
    </div>
</template>
