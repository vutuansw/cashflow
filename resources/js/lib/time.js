const dateFormat = new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
const timeFormat = new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit', hour12: false });

function parse(value) {
    if (!value) return null;
    const date = value instanceof Date ? value : new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
}

/** "1998-03-12" hoặc ISO thành "12/03/1998", xem plan/06 mục 1. */
export function formatDate(value) {
    const date = parse(value);
    return date ? dateFormat.format(date) : '';
}

/** "08/11/2026 14:02". */
export function formatDateTime(value) {
    const date = parse(value);
    return date ? `${dateFormat.format(date)} ${timeFormat.format(date)}` : '';
}

/** Mốc tương đối trên timeline: "Hôm nay", "Hôm qua", "3 ngày trước", rồi "08/11". */
export function relativeDay(value) {
    const date = parse(value);
    if (!date) return '';
    const startOf = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
    const days = Math.round((startOf(new Date()) - startOf(date)) / 86400000);
    if (days === 0) return 'Hôm nay';
    if (days === 1) return 'Hôm qua';
    if (days > 1 && days < 7) return `${days} ngày trước`;
    // Tự ghép dd/mm: Intl vi-VN không có năm thì in "03-11".
    const pad = (n) => String(n).padStart(2, '0');
    return date.getFullYear() === new Date().getFullYear() ? `${pad(date.getDate())}/${pad(date.getMonth() + 1)}` : dateFormat.format(date);
}

/** Nhãn mốc tháng trên timeline: "Tháng 11/2026". */
export function monthLabel(value) {
    const date = parse(value);
    return date ? `Tháng ${date.getMonth() + 1}/${date.getFullYear()}` : '';
}

/** Giá trị cho <input type="datetime-local">: "2026-11-08T14:02" theo giờ máy. */
export function toLocalInput(value = new Date()) {
    const date = parse(value) ?? new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}
