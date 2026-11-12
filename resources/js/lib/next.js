/** Đường dẫn quay lại sau đăng nhập/đăng ký: chỉ nhận đường dẫn nội bộ "/...", không nhận "//host". */
export function nextPath(route) {
    const next = route.query.next;
    return typeof next === 'string' && next.startsWith('/') && !next.startsWith('//') ? next : null;
}

/** Thêm ?next=... vào link nếu có. */
export function withNext(path, next) {
    return next ? `${path}?${new URLSearchParams({ next })}` : path;
}

/**
 * Trang đăng nhập mở từ một trang nhóm (next = "/{ma-nhom}..."): "Tạo tài khoản" dẫn tới link nhóm
 * /{ma-nhom}/register để vừa tạo tài khoản vừa xin vào nhóm (plan/30 mục 1). Đoạn đầu trùng một trang
 * cố định của app (groups, system, register...) thì không phải mã nhóm.
 */
export function registerPathFor(next, router) {
    const segment = next?.split(/[/?#]/)[1];
    if (!segment) return withNext('/register', next);
    const fixed = new Set(router.getRoutes().map((r) => r.path.split('/')[1]).filter((s) => s && !s.startsWith(':')));
    return fixed.has(segment) ? withNext('/register', next) : `/${segment}/register`;
}
