import { reactive } from 'vue';
import { adminApi } from './api.js';

// Số giao dịch SePay chờ duyệt, hiện chấm đỏ trên menu "Chờ duyệt" (plan/05 mục 4).
export const pending = reactive({ count: 0, code: null });

export async function refreshPending(code) {
    try {
        pending.count = (await adminApi(code, '/sepay/count')).pending;
        pending.code = code;
    } catch {
        // Không quan trọng: thiếu chấm đỏ không chặn việc gì.
    }
}
