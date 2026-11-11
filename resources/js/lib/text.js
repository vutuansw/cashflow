/** Bỏ dấu tiếng Việt, chữ thường: "Nguyễn Thị Lan" thành "nguyen thi lan". Khớp VietnameseText::fold phía PHP. */
export function fold(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'd')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * Tách văn bản thành các đoạn chữ và link (http/https, www.) để hiện link bấm được mà không dùng v-html.
 * Dấu câu dính cuối link (. , ! ? ) ]) được trả về phần chữ. Xem plan/21 mục 7.
 *
 * @returns {{ text: string, href?: string }[]}
 */
export function linkify(value) {
    const text = String(value ?? '');
    const parts = [];
    const re = /\b(?:https?:\/\/|www\.)[^\s<>"]+/gi;
    let last = 0;
    for (const m of text.matchAll(re)) {
        let url = m[0];
        const trail = url.match(/[.,!?;:)\]'"]+$/)?.[0] ?? '';
        if (trail) url = url.slice(0, -trail.length);
        if (m.index > last) parts.push({ text: text.slice(last, m.index) });
        parts.push({ text: url, href: url.startsWith('www.') ? `https://${url}` : url });
        last = m.index + url.length;
    }
    if (last < text.length) parts.push({ text: text.slice(last) });
    return parts;
}
