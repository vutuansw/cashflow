import { reactive } from 'vue';
import { ApiError } from './api.js';

// Gọi /api/system: phiên đăng nhập riêng, CSRF qua header X-CSRF-TOKEN lấy từ /api/system/csrf
// (không dùng cookie XSRF-TOKEN của khu nhóm). Xem plan/02 mục 6.
export const systemSession = reactive({ loaded: false, admin: null, csrf: null });

async function raw(path, { method = 'GET', body } = {}) {
    const response = await fetch(`/api/system${path}`, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(systemSession.csrf ? { 'X-CSRF-TOKEN': systemSession.csrf } : {}),
            ...(body ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = response.status === 204 ? null : await response.json().catch(() => null);
    if (!response.ok) throw new ApiError(response.status, data);
    return data;
}

export async function systemApi(path, options = {}) {
    if (!systemSession.csrf && options.method && options.method !== 'GET') {
        systemSession.csrf = (await raw('/csrf')).token;
    }
    try {
        return await raw(path, options);
    } catch (e) {
        // Token hết hạn (419): lấy lại một lần rồi thử lại.
        if (e.status === 419) {
            systemSession.csrf = (await raw('/csrf')).token;
            return raw(path, options);
        }
        if (e.status === 401) systemSession.admin = null;
        throw e;
    }
}

export async function loadSystemMe() {
    if (systemSession.loaded) return systemSession.admin;
    try {
        systemSession.admin = (await raw('/me')).data;
        systemSession.csrf = (await raw('/csrf')).token;
    } catch {
        systemSession.admin = null;
    }
    systemSession.loaded = true;
    return systemSession.admin;
}
