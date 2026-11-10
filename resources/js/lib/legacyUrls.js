// Chuyển URL tiếng Việt cũ sang URL tiếng Anh, xem plan/12.
// Giữ để link đã gửi qua Zalo/email, mã QR chiến dịch đã in, bookmark, PWA đã cài vẫn mở được.
// Hạn bỏ: sau 05/2027.

const EXACT = {
    '/dang-nhap': '/login',
    '/dang-ky': '/register',
    '/quen-mat-khau': '/forgot-password',
    '/dat-lai-mat-khau': '/reset-password',
    '/doi-mat-khau': '/change-password',
    '/nhom': '/groups',
    '/nhom/moi': '/groups/new',
    '/ca-nhan': '/profile',
    '/system/dang-nhap': '/system/login',
    '/system/nhom': '/system/groups',
    '/system/nhom/moi': '/system/groups/new',
    '/system/chi-phi-ai': '/system/ai-costs',
    '/system/nhat-ky': '/system/audit-logs',
    '/system/quan-tri-vien': '/system/admins',
    '/system/tai-khoan': '/system/account',
};

// Phần sau /:group/quan-tri. Thứ tự quan trọng: đoạn dài khớp trước.
const ADMIN = [
    [/^\/danh-ba\/nhap$/, '/contacts/import'],
    [/^\/danh-ba$/, '/contacts'],
    [/^\/tai-khoan$/, '/accounts'],
    [/^\/loai-thu-chi$/, '/categories'],
    [/^\/cai-dat$/, '/settings'],
    [/^\/them$/, '/more'],
    [/^\/tao-phieu$/, '/transactions/new'],
    [/^\/phieu\/moi$/, '/transactions/new'],
    [/^\/phieu(\/\d+)?$/, '/transactions$1'],
    [/^\/cho-duyet$/, '/sepay-queue'],
    [/^\/chien-dich\/moi$/, '/campaigns/new'],
    [/^\/chien-dich\/(\d+)\/sua$/, '/campaigns/$1/edit'],
    [/^\/chien-dich(\/\d+)?$/, '/campaigns$1'],
    [/^\/bao-cao$/, '/reports'],
    [/^$/, ''],
];

const REPORTS = { 'so-quy': 'ledger', 'theo-loai': 'by-category', 'theo-thang': 'by-month', campaign: 'campaign' };
const KEYS = { ky: 'period', moc: 'at', moi: 'welcome', chien_dich: 'campaign', thu_chi: 'type', nguon: 'source', tim: 'q', da_xoa: 'trashed' };

function mapPath(path) {
    if (EXACT[path]) return EXACT[path];

    let m = path.match(/^\/system\/nhom\/(\d+)$/);
    if (m) return `/system/groups/${m[1]}`;

    m = path.match(/^\/([^/]+)\/quan-tri(\/.*)?$/);
    if (m) {
        const rest = m[2] ?? '';
        for (const [re, to] of ADMIN) if (re.test(rest)) return `/${m[1]}/admin${rest.replace(re, to)}`;
        return `/${m[1]}/admin`;
    }

    m = path.match(/^\/([^/]+)\/(thong-ke|chien-dich|thanh-vien)(\/.*)?$/);
    if (m) {
        const seg = { 'thong-ke': 'stats', 'chien-dich': 'campaigns', 'thanh-vien': 'members' }[m[2]];
        return `/${m[1]}/${seg}${m[3] ?? ''}`;
    }
    return null;
}

// "loai" nghĩa khác nhau tùy trang: thu/chi khi tạo phiếu, mã loại ở danh sách phiếu, tab ở hàng chờ, kiểu báo cáo.
function mapQuery(newPath, oldPath, query) {
    const out = {};
    for (const [k, v] of Object.entries(query)) {
        if (k !== 'loai') {
            out[KEYS[k] ?? k] = v;
            continue;
        }
        if (newPath.endsWith('/transactions/new')) out.type = v === 'thu' ? 'income' : 'expense';
        else if (newPath.endsWith('/transactions')) out.category = v;
        else if (newPath.endsWith('/sepay-queue')) out.source = v;
        else if (newPath.endsWith('/reports')) out.report = REPORTS[v] ?? v;
    }
    if (oldPath.endsWith('/tao-phieu')) out.type = 'expense';
    return out;
}

/** Trả về đích mới nếu `to` là URL cũ, không thì null. */
export function legacyRedirect(to) {
    const path = mapPath(to.path);
    if (!path) return null;
    return { path, query: mapQuery(path, to.path, to.query), hash: to.hash, replace: true };
}
