import { reactive } from 'vue';

// Gợi ý "Thêm vào màn hình chính" (plan/06 mục 4). Android: giữ sự kiện
// beforeinstallprompt để bấm nút là cài; iPhone: chỉ hiện hướng dẫn Chia sẻ.
export const install = reactive({ prompt: null, installed: false });

const KEY = 'wepo:install-hint-dismissed';

export function standalone() {
    return window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

export function isIos() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent) && !/crios|fxios/i.test(navigator.userAgent);
}

export function dismissed() {
    try {
        return localStorage.getItem(KEY) === '1';
    } catch {
        return true;
    }
}

export function dismiss() {
    try {
        localStorage.setItem(KEY, '1');
    } catch {
        // Trình duyệt chặn lưu trữ: chỉ ẩn trong lần này.
    }
}

// Nhóm vừa xem gần nhất: mở WePo từ icon màn hình chính thì vào lại nhóm này (khách chưa có tài khoản cũng vậy).
const LAST_GROUP = 'wepo:last-group';

export function rememberGroup(code) {
    try {
        localStorage.setItem(LAST_GROUP, code);
    } catch {
        // Trình duyệt chặn lưu trữ: bỏ qua.
    }
}

export function lastGroup() {
    try {
        return localStorage.getItem(LAST_GROUP);
    } catch {
        return null;
    }
}

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    install.prompt = event;
});
window.addEventListener('appinstalled', () => {
    install.installed = true;
    install.prompt = null;
});
