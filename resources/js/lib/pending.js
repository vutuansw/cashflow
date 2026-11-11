import { reactive } from 'vue';
import { adminApi } from './api.js';

// Số giao dịch SePay chờ duyệt, hiện chấm đỏ trên menu "Chờ duyệt" (plan/05 mục 4).
// joins: người xin vào nhóm qua link, chấm đỏ ở mục "Danh bạ" (plan/13).
// posts: bài đăng thành viên chờ duyệt, chấm đỏ ở mục "Duyệt bài đăng" (plan/21).
export const pending = reactive({ count: 0, joins: 0, posts: 0, code: null });

export async function refreshPending(code) {
    try {
        const res = await adminApi(code, '/sepay/count');
        pending.count = res.pending;
        pending.joins = res.joins ?? 0;
        pending.posts = res.posts ?? 0;
        pending.code = code;
    } catch {
        // Không quan trọng: thiếu chấm đỏ không chặn việc gì.
    }
}
