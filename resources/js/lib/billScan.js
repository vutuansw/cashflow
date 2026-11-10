import { reactive } from 'vue';
import { adminApi } from './api.js';
import { shrinkImage } from './image.js';

// Trạng thái "Đang đọc bill…" dùng chung cho nút + và trang tạo phiếu (plan/04 mục 1).
export const billReading = reactive({ busy: false, preview: null });

/** Thu nhỏ ảnh, gửi lên cho AI đọc. Trả về lượt đọc: { id, status, readable, draft, bill_url, message }. */
export async function readBill(code, file) {
    billReading.busy = true;
    const small = await shrinkImage(file, 1600, 0.85);
    billReading.preview = URL.createObjectURL(small);
    try {
        const body = new FormData();
        body.append('bill', small);
        return await adminApi(code, '/bill-scans', { method: 'POST', body });
    } finally {
        URL.revokeObjectURL(billReading.preview);
        billReading.preview = null;
        billReading.busy = false;
    }
}
