import { reactive } from 'vue';
import { api } from './api.js';
import { lastGroup } from './install.js';

// Tài khoản đang đăng nhập và danh sách nhóm của họ. null = chưa đăng nhập.
export const session = reactive({ loaded: false, me: null });

export async function loadMe(force = false) {
    if (session.loaded && !force) return session.me;
    try {
        session.me = (await api('/me')).data;
    } catch {
        session.me = null;
    }
    session.loaded = true;
    return session.me;
}

export function setMe(me) {
    session.me = me;
    session.loaded = true;
}

export async function logout() {
    await api('/logout', { method: 'POST' }).catch(() => {});
    session.me = null;
}

/** Vai trò của tôi trong nhóm có mã code, hoặc null. */
export function roleIn(code) {
    return session.me?.groups?.find((g) => g.code === code)?.role ?? null;
}

export function canManage(code) {
    return ['owner', 'admin'].includes(roleIn(code));
}

/** Sau đăng nhập: vào thẳng timeline nhóm nếu chỉ có một nhóm (mọi vai trò, plan/16), nhiều nhóm thì chọn nhóm. */
/**
 * Trang của tôi (plan/24): trang thành viên của mình ở nhóm vừa vào gần nhất, không có thì nhóm đầu danh sách.
 * Chưa thuộc nhóm nào thì không có trang cá nhân, về trang chọn nhóm.
 */
export function myPagePath(me = session.me) {
    if (!me) return '/login';
    const last = lastGroup();
    const group = me.groups.find((g) => g.code === last) ?? me.groups[0];
    return group ? `/${group.code}/members/${group.member_handle}` : '/groups';
}

export function homePath(me = session.me) {
    if (!me) return '/login';
    if (me.groups.length === 1) return `/${me.groups[0].code}`;
    return '/groups';
}
