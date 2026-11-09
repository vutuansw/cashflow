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
