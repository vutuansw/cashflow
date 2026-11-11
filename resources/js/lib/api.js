// Gọi API cùng domain bằng session cookie; Laravel tự đặt cookie XSRF-TOKEN cho CSRF.
function xsrfToken() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export class ApiError extends Error {
    constructor(status, data) {
        super(data?.message || 'Có lỗi xảy ra, bạn thử lại nhé.');
        this.status = status;
        this.errors = data?.errors || {};
    }
}

export async function api(path, { method = 'GET', body, headers = {} } = {}) {
    const isForm = body instanceof FormData;
    const response = await fetch(`/api${path}`, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
            ...(body && !isForm ? { 'Content-Type': 'application/json' } : {}),
            ...headers,
        },
        body: body === undefined ? undefined : isForm ? body : JSON.stringify(body),
    });

    const data = response.status === 204 ? null : await response.json().catch(() => null);
    if (!response.ok) {
        throw new ApiError(response.status, data);
    }
    return data;
}

/** API quản trị của nhóm đang xem: adminApi('chidoan-a12', '/contacts'). */
export function adminApi(code, path, options) {
    return api(`/g/${encodeURIComponent(code)}/admin${path}`, options);
}

/**
 * Gửi FormData có ảnh kèm tiến độ tải lên (fetch không báo được tiến độ). onProgress nhận số 0..1.
 * Lỗi trả về giống api(): ApiError có status và errors theo trường. Xem plan/21 mục 7.
 */
export function apiUpload(path, form, { method = 'POST', onProgress } = {}) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, `/api${path}`);
        xhr.withCredentials = true;
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-XSRF-TOKEN', xsrfToken());
        if (onProgress) xhr.upload.onprogress = (e) => e.lengthComputable && onProgress(e.loaded / e.total);
        xhr.onload = () => {
            let data = null;
            try {
                data = xhr.status === 204 ? null : JSON.parse(xhr.responseText);
            } catch {
                data = null;
            }
            if (xhr.status >= 200 && xhr.status < 300) resolve(data);
            else reject(new ApiError(xhr.status, data));
        };
        // Mất mạng: không phải ApiError, useForm báo "Không kết nối được máy chủ".
        xhr.onerror = () => reject(new TypeError('Network error'));
        xhr.send(form);
    });
}
