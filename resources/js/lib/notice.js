import { reactive } from 'vue';

// Thông báo ngắn nổi ở đáy màn hình.
export const notice = reactive({ items: [] });

let id = 0;
export function toast(text, type = 'success') {
    const item = { id: ++id, text, type };
    notice.items.push(item);
    setTimeout(() => {
        notice.items = notice.items.filter((i) => i.id !== item.id);
    }, 3500);
}
