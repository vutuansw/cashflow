/**
 * Thu nhỏ ảnh ngay trên điện thoại trước khi tải lên để đỡ tốn 4G (plan/03 mục 3).
 * Cạnh dài về tối đa 2000px, JPEG 0,85; máy chủ còn thu tiếp về 1600px webp.
 * Trình duyệt không đọc được (HEIC trên Android chẳng hạn) thì gửi nguyên file.
 */
export async function shrinkImage(file, max = 2000, quality = 0.85) {
    if (!file.type.startsWith('image/') || file.type === 'image/gif') return file;
    try {
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        const scale = Math.min(1, max / Math.max(bitmap.width, bitmap.height));
        if (scale === 1 && file.size < 1.5 * 1024 * 1024 && file.type === 'image/jpeg') return file;

        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close?.();

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
        if (!blob) return file;
        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
    } catch {
        return file;
    }
}

/** Ảnh xem trước tạm thời; nhớ gọi URL.revokeObjectURL khi bỏ. */
export function previewUrl(file) {
    return URL.createObjectURL(file);
}
