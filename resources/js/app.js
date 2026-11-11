import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import { legacyRedirect } from './lib/legacyUrls.js';
import { loadSystemMe } from './lib/systemApi.js';
import { lastGroup } from './lib/install.js';
import { canManage, homePath, loadMe, session } from './lib/session.js';

// Bảng route của SPA, xem plan/plan.md mục 4 và plan/06 mục 3.
// meta.auth: phải đăng nhập · meta.guest: chỉ khi chưa đăng nhập · meta.admin: trưởng nhóm/QTV của nhóm.
const admin = { auth: true, admin: true };
const soon = (title) => ({ component: () => import('./pages/admin/ComingSoonPage.vue'), props: { title }, meta: admin });

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', component: () => import('./pages/HomePage.vue') },
        { path: '/login', component: () => import('./pages/auth/LoginPage.vue'), meta: { guest: true } },
        { path: '/register', component: () => import('./pages/auth/RegisterPage.vue'), meta: { guest: true } },
        { path: '/forgot-password', component: () => import('./pages/auth/ForgotPasswordPage.vue'), meta: { guest: true } },
        { path: '/reset-password', component: () => import('./pages/auth/ResetPasswordPage.vue'), meta: { guest: true } },
        { path: '/change-password', component: () => import('./pages/auth/ChangePasswordPage.vue'), meta: { auth: true } },
        // Tạo nhóm: khách mở được, trang tự báo cần tạo tài khoản trước (plan/11).
        { path: '/groups/new', component: () => import('./pages/GroupNewPage.vue') },
        { path: '/groups', component: () => import('./pages/GroupPickerPage.vue'), meta: { auth: true } },
        { path: '/profile', component: () => import('./pages/ProfilePage.vue'), meta: { auth: true } },

        { path: '/:group/admin', component: () => import('./pages/admin/OverviewPage.vue'), meta: admin },
        { path: '/:group/admin/contacts', component: () => import('./pages/admin/ContactsPage.vue'), meta: admin },
        { path: '/:group/admin/contacts/import', component: () => import('./pages/admin/ContactImportPage.vue'), meta: admin },
        { path: '/:group/admin/accounts', component: () => import('./pages/admin/MembersPage.vue'), meta: admin },
        { path: '/:group/admin/categories', component: () => import('./pages/admin/CategoriesPage.vue'), meta: admin },
        { path: '/:group/admin/settings', component: () => import('./pages/admin/SettingsPage.vue'), meta: admin },
        { path: '/:group/admin/more', component: () => import('./pages/admin/MorePage.vue'), meta: admin },
        { path: '/:group/admin/transactions', component: () => import('./pages/admin/TransactionsPage.vue'), meta: admin },
        { path: '/:group/admin/transactions/new', component: () => import('./pages/admin/TransactionFormPage.vue'), meta: admin },
        { path: '/:group/admin/transactions/:id(\\d+)', component: () => import('./pages/admin/TransactionFormPage.vue'), meta: admin },
        { path: '/:group/admin/sepay-queue', component: () => import('./pages/admin/SepayQueuePage.vue'), meta: admin },
        { path: '/:group/admin/campaigns', component: () => import('./pages/admin/CampaignsPage.vue'), meta: admin },
        { path: '/:group/admin/campaigns/new', component: () => import('./pages/admin/CampaignFormPage.vue'), meta: admin },
        { path: '/:group/admin/campaigns/:id(\\d+)', component: () => import('./pages/admin/CampaignDetailPage.vue'), meta: admin },
        { path: '/:group/admin/campaigns/:id(\\d+)/edit', component: () => import('./pages/admin/CampaignFormPage.vue'), meta: admin },
        { path: '/:group/admin/reports', component: () => import('./pages/admin/ReportsPage.vue'), meta: admin },

        { path: '/system/login', component: () => import('./pages/system/LoginPage.vue'), meta: { system: 'guest' } },
        { path: '/system', component: () => import('./pages/system/OverviewPage.vue'), meta: { system: true } },
        { path: '/system/groups', component: () => import('./pages/system/GroupsPage.vue'), meta: { system: true } },
        { path: '/system/groups/new', component: () => import('./pages/system/GroupCreatePage.vue'), meta: { system: true } },
        { path: '/system/groups/:id(\\d+)', component: () => import('./pages/system/GroupDetailPage.vue'), meta: { system: true } },
        { path: '/system/ai-costs', component: () => import('./pages/system/AiCostsPage.vue'), meta: { system: true } },
        { path: '/system/audit-logs', component: () => import('./pages/system/AuditLogsPage.vue'), meta: { system: true } },
        { path: '/system/admins', component: () => import('./pages/system/AdminsPage.vue'), meta: { system: true } },
        { path: '/system/account', component: () => import('./pages/system/AccountPage.vue'), meta: { system: true } },
        // Link đăng ký vào nhóm (plan/13). "register" không thể là mã nhóm nên /register vẫn là trang đăng ký thường.
        { path: '/:group/register', component: () => import('./pages/auth/JoinGroupPage.vue') },
        { path: '/:group/stats', component: () => import('./pages/GroupStatsPage.vue') },
        { path: '/:group/campaigns/:slug', component: () => import('./pages/CampaignPage.vue') },
        { path: '/:group/members', component: () => import('./pages/GroupMembersPage.vue') },
        { path: '/:group/members/:id(\\d+)', component: () => import('./pages/MemberPage.vue') },
        { path: '/:group', component: () => import('./pages/GroupHomePage.vue') },
        { path: '/:pathMatch(.*)*', component: () => import('./pages/NotFoundPage.vue') },
    ],
    // Quay lại (‹ hoặc nút back): chờ danh sách tải xong đủ dài rồi mới cuộn về chỗ cũ, tối đa 2 giây.
    scrollBehavior(to, from, saved) {
        if (!saved) return to.path === from.path ? false : { top: 0 }; // đổi bộ lọc trên URL: giữ nguyên chỗ đang xem
        return new Promise((resolve) => {
            const started = Date.now();
            const tick = () => {
                const tallEnough = document.documentElement.scrollHeight >= saved.top + window.innerHeight;
                if (tallEnough || Date.now() - started > 2000) resolve(saved);
                else setTimeout(tick, 100);
            };
            tick();
        });
    },
});

router.beforeEach(async (to) => {
    // URL tiếng Việt cũ → URL mới (plan/12). Đặt đầu tiên vì /dang-nhap sẽ khớp nhầm route /:group.
    const moved = legacyRedirect(to);
    if (moved) return moved;

    // Khu /system dùng phiên đăng nhập riêng, không dính tới tài khoản nhóm.
    if (to.meta.system) {
        const admin = await loadSystemMe();
        if (to.meta.system === 'guest') return admin ? '/system' : true;
        return admin ? true : { path: '/system/login', query: { next: to.fullPath } };
    }

    await loadMe();
    const me = session.me;

    // Mở từ icon màn hình chính (manifest start_url /groups?pwa=1): vào thẳng nhóm.
    // Có tài khoản: nhóm của mình (nhiều nhóm thì nhóm vừa xem, không có thì trang chọn nhóm).
    // Khách: nhóm công khai vừa xem, chưa xem nhóm nào thì trang chủ.
    if (to.path === '/groups' && to.query.pwa) {
        const last = lastGroup();
        if (!me) return last ? `/${last}` : '/';
        if (me.groups.length > 1 && last && me.groups.some((g) => g.code === last)) return `/${last}`;
        return homePath(me) === '/groups' ? '/groups' : homePath(me);
    }

    if (to.meta.guest && me) return homePath(me);
    if (to.meta.auth && !me) return { path: '/login', query: { next: to.fullPath } };
    if (me?.must_change_password && to.path !== '/change-password') return '/change-password';
    if (to.meta.admin && !canManage(to.params.group)) return `/${to.params.group}`;
    return true;
});

// Sau mỗi lần deploy, vite build xóa file JS cũ. Trang đang mở từ trước (vd. /login) chuyển sang trang khác sẽ
// tải file cũ, bị 404 và đứng im. Khi đó tải lại hẳn trang đích để lấy bản mới; chặn lặp nếu vừa tải lại < 10 giây.
const CHUNK_ERROR = /dynamically imported module|Importing a module script failed|Unable to preload CSS/i;
router.onError((error, to) => {
    if (!CHUNK_ERROR.test(error?.message ?? '')) return;
    let last = 0;
    try { last = Number(sessionStorage.getItem('wepo-chunk-reload')) || 0; } catch {}
    if (Date.now() - last < 10_000) return;
    try { sessionStorage.setItem('wepo-chunk-reload', String(Date.now())); } catch {}
    window.location.assign(to.fullPath);
});

createApp(App).use(router).mount('#app');

// PWA: đăng ký service worker (chỉ cache file tĩnh, xem public/sw.js và plan/06 mục 4).
if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
