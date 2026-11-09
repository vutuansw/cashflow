const dateFormat = new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });

/** "1998-03-12" hoặc ISO thành "12/03/1998", xem plan/06 mục 1. */
export function formatDate(value) {
    if (!value) return '';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '' : dateFormat.format(date);
}
