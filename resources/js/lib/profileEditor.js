import { reactive, ref } from 'vue';
import { api, ApiError } from './api.js';
import { ignore, useForm } from './form.js';
import { shrinkImage } from './image.js';
import { toast } from './notice.js';
import { session, setMe } from './session.js';

// Chủ trang tự sửa trang của mình ngay trên trang thành viên (plan/24): đổi/gỡ ảnh bìa, ảnh đại diện,
// sửa họ tên, lời giới thiệu, email. Lưu xong cập nhật session.me nên phần đầu trang và Giới thiệu đổi ngay.
// Giao diện (input chọn ảnh, menu ảnh, sheet Sửa thông tin) ở ProfileEditSheets.vue.
const LABEL = { avatar: 'ảnh đại diện', cover: 'ảnh bìa' };

/** @param {(url: string) => void} onView  phóng to ảnh (trang cha giữ PhotoViewer) */
export function useProfileEditor(onView) {
    const inputs = { avatar: ref(null), cover: ref(null) };
    const imageMenu = ref(null); // 'avatar' | 'cover' khi đang mở menu ảnh
    const uploading = ref(null);
    const editing = ref(false);
    const form = reactive({ name: '', bio: '', email: '' });
    const { state, submit } = useForm();

    /** Chưa có ảnh thì mở ngay chọn ảnh; có rồi thì hiện menu Xem / Đổi / Gỡ. */
    function tapImage(kind) {
        if (uploading.value) return;
        if (session.me?.[`${kind}_url`]) imageMenu.value = kind;
        else pick(kind);
    }

    function pick(kind) {
        imageMenu.value = null;
        inputs[kind].value?.click();
    }

    function view(kind) {
        imageMenu.value = null;
        onView(session.me[`${kind}_url`]);
    }

    async function upload(kind, event) {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;
        uploading.value = kind;
        try {
            const body = new FormData();
            body.append('kind', kind);
            body.append('image', await shrinkImage(file, 2000));
            setMe((await api('/me/images', { method: 'POST', body })).data);
            toast(`Đã đổi ${LABEL[kind]}.`);
        } catch (e) {
            toast(e instanceof ApiError ? Object.values(e.errors ?? {})[0]?.[0] ?? e.message : 'Không kết nối được máy chủ, bạn thử lại nhé.', 'error');
        } finally {
            uploading.value = null;
        }
    }

    async function removeImage(kind) {
        imageMenu.value = null;
        if (!confirm(`Gỡ ${LABEL[kind]}?`)) return;
        try {
            setMe((await api(`/me/images/${kind}`, { method: 'DELETE' })).data);
            toast(`Đã gỡ ${LABEL[kind]}.`);
        } catch {
            toast(`Chưa gỡ được ${LABEL[kind]}, bạn thử lại nhé.`, 'error');
        }
    }

    function edit() {
        const me = session.me;
        Object.assign(form, { name: me.name, bio: me.bio ?? '', email: me.email ?? '' });
        state.errors = {};
        editing.value = true;
    }

    async function save() {
        const res = await submit(() => api('/me', { method: 'PUT', body: form })).catch(ignore);
        if (!res) return;
        setMe(res.data);
        editing.value = false;
        toast('Đã lưu.');
    }

    return { inputs, imageMenu, uploading, editing, form, state, tapImage, pick, view, upload, removeImage, edit, save };
}
