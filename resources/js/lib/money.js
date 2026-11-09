const vnd = new Intl.NumberFormat('vi-VN');

/** 1500000 thành "1.500.000 đ". */
export function formatMoney(amount) {
    return `${vnd.format(Math.round(Number(amount) || 0))} đ`;
}

/** Rút gọn cho chỗ chật: 1500000 thành "1,5tr", 250000 thành "250k". */
export function formatMoneyShort(amount) {
    const n = Math.abs(Number(amount) || 0);
    const sign = Number(amount) < 0 ? '-' : '';
    if (n >= 1e9) return `${sign}${trim(n / 1e9)}tỷ`;
    if (n >= 1e6) return `${sign}${trim(n / 1e6)}tr`;
    if (n >= 1e3) return `${sign}${trim(n / 1e3)}k`;
    return `${sign}${n}`;
}

function trim(value) {
    return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 1 }).format(value);
}
