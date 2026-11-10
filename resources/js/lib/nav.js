import { ArrowLeftRight, BookUser, Ellipsis, Eye, FileBarChart, Inbox, LayoutDashboard, Megaphone, Plus, Settings, Shapes, ShieldCheck } from 'lucide-vue-next';

// Menu khu quản trị nhóm, xem plan/06 mục 2. Dùng chung cho sidebar, tab đáy và trang "Thêm".
export function adminMenu(code) {
    const base = `/${code}/admin`;
    return [
        { to: base, label: 'Tổng quan', icon: LayoutDashboard, exact: true, tab: true },
        { to: `${base}/transactions`, label: 'Phiếu thu chi', short: 'Phiếu', icon: ArrowLeftRight, tab: true },
        { to: `${base}/sepay-queue`, label: 'Chờ duyệt', icon: Inbox, tab: true },
        { to: `${base}/campaigns`, label: 'Chiến dịch', icon: Megaphone },
        { to: `${base}/contacts`, label: 'Danh bạ', icon: BookUser },
        { to: `${base}/accounts`, label: 'Tài khoản & vai trò', icon: ShieldCheck },
        { to: `${base}/reports`, label: 'Báo cáo', icon: FileBarChart },
        { to: `${base}/categories`, label: 'Loại thu chi', icon: Shapes },
        { to: `${base}/settings`, label: 'Cài đặt nhóm', icon: Settings },
        { to: `/${code}`, label: 'Xem timeline', icon: Eye, exact: true },
    ];
}

export function adminTabs(code) {
    const menu = adminMenu(code);
    return [
        { ...menu[0] },
        { ...menu[1], label: 'Phiếu' },
        { to: `/${code}/admin/transactions/new?type=expense`, label: 'Tạo', icon: Plus, primary: true },
        { ...menu[2] },
        { to: `/${code}/admin/more`, label: 'Thêm', icon: Ellipsis },
    ];
}
