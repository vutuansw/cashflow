/** Đường dẫn quay lại sau đăng nhập/đăng ký: chỉ nhận đường dẫn nội bộ "/...", không nhận "//host". */
export function nextPath(route) {
    const next = route.query.next;
    return typeof next === 'string' && next.startsWith('/') && !next.startsWith('//') ? next : null;
}

/** Thêm ?next=... vào link nếu có. */
export function withNext(path, next) {
    return next ? `${path}?${new URLSearchParams({ next })}` : path;
}
