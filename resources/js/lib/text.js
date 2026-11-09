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
