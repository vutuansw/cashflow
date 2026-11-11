// Tự lấy bản mới sau deploy, xem plan/27.
// App mở từ icon iPhone không bao giờ tải lại trang: iOS chỉ đánh thức trang cũ, vue-router đổi trang ngay trên máy,
// service worker còn trả file JS cũ từ cache. Nên trang tự hỏi server mã bản build mới nhất (/api/app, không cache)
// và so với mã của chính nó (meta wepo-build). Khác thì lần chuyển trang kế tiếp tải lại hẳn trang đích.

const CURRENT = document.querySelector('meta[name="wepo-build"]')?.content || null; // null khi chạy dev: không kiểm tra
const ON_RESUME = 30_000; // app quay lại màn hình: hỏi tối đa 1 lần mỗi 30 giây
const ON_NAVIGATE = 5 * 60_000; // chuyển trang: hỏi khi đã quá 5 phút chưa hỏi
const RELOAD_KEY = 'wepo-reload';

let lastCheck = Date.now(); // vừa tải trang xong thì chắc chắn là bản mới nhất
let stale = false;
let checking = false;

async function check() {
    if (!CURRENT || stale || checking) return;
    checking = true;
    lastCheck = Date.now();
    try {
        const response = await fetch('/api/app', { cache: 'no-store', headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        const { build } = await response.json();
        if (build && build !== CURRENT) stale = true;
    } catch {
        // Mất mạng hoặc server đang deploy: bỏ qua, lần sau hỏi lại.
    } finally {
        checking = false;
    }
}

export function startUpdateCheck() {
    if (!CURRENT) return;
    const resumed = () => {
        if (document.visibilityState === 'visible' && Date.now() - lastCheck > ON_RESUME) check();
    };
    document.addEventListener('visibilitychange', resumed);
    window.addEventListener('pageshow', (event) => event.persisted && resumed()); // Safari quay lại trang từ bộ nhớ đệm
}

/** Gọi trong router.beforeEach: lâu chưa hỏi thì hỏi (không chờ, kết quả dùng cho lần chuyển trang sau). */
export function checkOnNavigate() {
    if (Date.now() - lastCheck > ON_NAVIGATE) check();
}

export function hasUpdate() {
    return stale;
}

/**
 * Tải lại hẳn trang đích. Trả false nếu vừa tải lại chưa tới 10 giây (chặn lặp), khi đó cứ chuyển trang bình thường.
 * Trước khi đi, báo service worker xóa file /build/ cũ: trang mới trỏ tới file tên mới, file cũ chỉ còn chiếm chỗ.
 */
export function reloadTo(path) {
    let last = 0;
    try { last = Number(sessionStorage.getItem(RELOAD_KEY)) || 0; } catch {}
    if (Date.now() - last < 10_000) return false;
    try { sessionStorage.setItem(RELOAD_KEY, String(Date.now())); } catch {}
    navigator.serviceWorker?.controller?.postMessage({ type: 'purge-build' });
    window.location.assign(path);
    return true;
}
