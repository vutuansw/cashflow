<script setup>
import { computed, ref } from 'vue';
import { Eye, EyeOff, MoreHorizontal, Pencil, Trash2 } from 'lucide-vue-next';
import DropdownMenu from './DropdownMenu.vue';
import MemberName from './MemberName.vue';
import PhotoGrid from './PhotoGrid.vue';
import PostActions from './PostActions.vue';
import PostComments from './PostComments.vue';
import PostComposer from './PostComposer.vue';
import UserAvatar from './UserAvatar.vue';
import { api } from '../lib/api.js';
import { toast } from '../lib/notice.js';
import { linkify } from '../lib/text.js';
import { relativeTime } from '../lib/time.js';

// Một bài đăng thành viên trên timeline: avatar + tên người đăng, chữ (link bấm được), ảnh, tim, bình luận.
// Menu ⋯: người đăng sửa/xóa; QTV ẩn/bỏ ẩn, xóa. Xem plan/21 mục 7.
// Slot mặc định: nút thêm ở cuối thẻ (vd Duyệt / Từ chối ở trang duyệt bài).
const props = defineProps({ post: Object, code: String, viewer: Object });
const emit = defineEmits(['login', 'removed']);

const editing = ref(false);
const expanded = ref(false);
const comments = ref(null);

const parts = computed(() => linkify(props.post.body));
// Bài dài (nhiều chữ hoặc nhiều dòng) thì thu gọn 6 dòng, có nút "Xem thêm".
const long = computed(() => (props.post.body?.length ?? 0) > 400 || (props.post.body?.split('\n').length ?? 0) > 7);
const live = computed(() => props.post.status === 'published' && !props.post.hidden);
const hasMenu = computed(() => props.post.can_edit || props.post.can_delete || props.post.can_hide);

function saved(card) {
    Object.assign(props.post, card);
    editing.value = false;
    toast('Đã lưu bài.');
}

async function remove() {
    if (!confirm('Xóa bài này? Bình luận và lượt tim của bài cũng mất theo.')) return;
    try {
        await api(`/g/${props.code}/statuses/${props.post.id}`, { method: 'DELETE' });
        emit('removed', props.post);
        toast('Đã xóa bài.');
    } catch {
        toast('Chưa xóa được, bạn thử lại nhé.', 'error');
    }
}

async function toggleHide() {
    try {
        const card = await api(`/g/${props.code}/statuses/${props.post.id}/hide`, { method: 'POST', body: { hidden: !props.post.hidden } });
        Object.assign(props.post, card);
        toast(card.hidden ? 'Đã ẩn bài. Chỉ người đăng và quản trị viên còn thấy.' : 'Đã hiện lại bài.');
    } catch {
        toast('Chưa làm được, bạn thử lại nhé.', 'error');
    }
}
</script>

<template>
    <article class="card p-4" :class="{ 'border-dashed': !live }">
        <div class="flex items-start gap-3">
            <RouterLink v-if="post.author.member_handle" :to="`/${code}/members/${post.author.member_handle}`" class="shrink-0" :aria-label="`Trang của ${post.author.name}`">
                <UserAvatar :url="post.author.avatar_url" :name="post.author.name" class="!size-10" />
            </RouterLink>
            <UserAvatar v-else :url="post.author.avatar_url" :name="post.author.name" class="!size-10" />
            <div class="min-w-0 flex-1">
                <MemberName :name="post.author.name" :member-handle="post.author.member_handle" :code="code" class="font-semibold leading-snug" />
                <p class="text-xs text-muted-foreground">
                    {{ relativeTime(post.occurred_at) }}<template v-if="post.author.role_label"> · {{ post.author.role_label }}</template><template v-if="post.edited"> · đã sửa</template>
                </p>
            </div>
            <DropdownMenu v-if="hasMenu && !editing" label="Tùy chọn bài đăng">
                <template #trigger="{ open, toggle }">
                    <button type="button" class="-mr-2 -mt-2 icon-btn text-muted-foreground" aria-label="Tùy chọn bài đăng" aria-haspopup="menu" :aria-expanded="open" @click="toggle"><MoreHorizontal class="size-5" /></button>
                </template>
                <button v-if="post.can_edit" type="button" class="menu-item" role="menuitem" @click="editing = true"><Pencil class="size-5 text-muted-foreground" /> Sửa bài</button>
                <button v-if="post.can_hide" type="button" class="menu-item" role="menuitem" @click="toggleHide">
                    <component :is="post.hidden ? Eye : EyeOff" class="size-5 text-muted-foreground" /> {{ post.hidden ? 'Hiện lại bài' : 'Ẩn bài' }}
                </button>
                <button v-if="post.can_delete" type="button" class="menu-item text-expense" role="menuitem" @click="remove"><Trash2 class="size-5" /> Xóa bài</button>
            </DropdownMenu>
        </div>

        <!-- Trạng thái chỉ người đăng (và QTV) thấy -->
        <p v-if="post.status === 'pending'" class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800">
            {{ post.mine ? 'Đang chờ quản trị viên duyệt. Chỉ bạn và quản trị viên thấy bài này.' : 'Đang chờ duyệt.' }}
        </p>
        <p v-else-if="post.status === 'rejected'" class="mt-3 rounded-xl bg-expense/10 px-3 py-2 text-sm text-expense">
            Bài không được duyệt<template v-if="post.reject_reason">: {{ post.reject_reason }}</template>.<template v-if="post.mine"> Bạn có thể xóa bài này.</template>
        </p>
        <p v-else-if="post.hidden" class="mt-3 rounded-xl bg-muted px-3 py-2 text-sm text-muted-foreground">Bài đã bị quản trị viên ẩn. Chỉ người đăng và quản trị viên còn thấy.</p>

        <PostComposer v-if="editing" :code="code" :viewer="viewer" :post="post" @saved="saved" @cancel="editing = false" />
        <template v-else>
            <template v-if="post.body">
                <p class="mt-2 whitespace-pre-line break-words" :class="{ 'line-clamp-6': long && !expanded, 'text-[17px] leading-relaxed': !post.photos.length && post.body.length < 120 }">
                    <template v-for="(part, i) in parts" :key="i"><a v-if="part.href" :href="part.href" target="_blank" rel="noopener nofollow ugc" class="text-primary underline-offset-2 hover:underline">{{ part.text }}</a><template v-else>{{ part.text }}</template></template>
                </p>
                <button v-if="long && !expanded" type="button" class="min-h-8 text-sm font-semibold text-muted-foreground hover:text-foreground" @click="expanded = true">Xem thêm</button>
            </template>
            <PhotoGrid v-if="post.photos?.length" :photos="post.photos" class="mt-3" />
        </template>

        <template v-if="live && !editing">
            <PostActions :post="post" :code="code" :viewer="viewer" @login="emit('login')" @compose="comments?.compose()" />
            <PostComments ref="comments" :post="post" :code="code" :viewer="viewer" />
        </template>
        <slot />
    </article>
</template>
