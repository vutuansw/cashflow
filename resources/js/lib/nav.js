import { ArrowLeftRight, BookUser, Ellipsis, Eye, FileBarChart, Inbox, LayoutDashboard, Megaphone, Plus, Settings, Shapes, ShieldCheck } from 'lucide-vue-next';

// Menu khu quản trị nhóm, xem plan/06 mục 2. Dùng chung cho sidebar, tab đáy và trang "Thêm".
export function adminMenu(code) {
    const base = `/${code}/quan-tri`;
    return [
        { to: base, label: 'Tổng quan', icon: LayoutDashboard, exact: true, tab: true },
        { to: `${base}/phieu`, label: 'Phiếu thu chi', short: 'Phiếu', icon: ArrowLeftRight, tab: true },
        { to: `${base}/cho-duyet`, label: 'Chờ duyệt', icon: Inbox, tab: true },
        { to: `${base}/chien-dich`, label: 'Chiến dịch', icon: Megaphone },
        { to: `${base}/danh-ba`, label: 'Danh bạ', icon: BookUser },
        { to: `${base}/tai-khoan`, label: 'Tài khoản & vai trò', icon: ShieldCheck },
        { to: `${base}/bao-cao`, label: 'Báo cáo', icon: FileBarChart },
        { to: `${base}/loai-thu-chi`, label: 'Loại thu chi', icon: Shapes },
        { to: `${base}/cai-dat`, label: 'Cài đặt nhóm', icon: Settings },
        { to: `/${code}`, label: 'Xem timeline', icon: Eye, exact: true },
    ];
}

export function adminTabs(code) {
    const menu = adminMenu(code);
    return [
        { ...menu[0] },
        { ...menu[1], label: 'Phiếu' },
        { to: `/${code}/quan-tri/tao-phieu`, label: 'Tạo', icon: Plus, primary: true },
        { ...menu[2] },
        { to: `/${code}/quan-tri/them`, label: 'Thêm', icon: Ellipsis },
    ];
}
