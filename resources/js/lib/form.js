import { reactive } from 'vue';
import { ApiError } from './api.js';
import { toast } from './notice.js';

/**
 * Trạng thái gửi form: busy, lỗi theo trường từ Laravel (422), lỗi chung.
 * submit(fn) chạy fn, bắt ApiError và đổ lỗi vào errors.
 */
export function useForm() {
    const state = reactive({ busy: false, errors: {}, message: '' });

    async function submit(fn) {
        state.busy = true;
        state.errors = {};
        state.message = '';
        try {
            return await fn();
        } catch (e) {
            if (e instanceof ApiError) {
                state.errors = Object.fromEntries(Object.entries(e.errors).map(([k, v]) => [k, v[0]]));
                state.message = Object.keys(state.errors).length ? '' : e.message;
                if (state.message) toast(state.message, 'error');
            } else {
                state.message = 'Không kết nối được máy chủ, bạn kiểm tra mạng rồi thử lại.';
                toast(state.message, 'error');
            }
            throw e;
        } finally {
            state.busy = false;
        }
    }

    return { state, submit };
}

/** Bỏ qua lỗi đã được useForm hiển thị, để await submit() không văng lỗi đỏ ra console. */
export const ignore = () => {};
