import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import { loadSystemMe } from './lib/systemApi.js';
import './lib/install.js';
import { canManage, homePath, loadMe, session } from './lib/session.js';

// Bảng route của SPA, xem plan/plan.md mục 4 và plan/06 mục 3.
// meta.auth: phải đăng nhập · meta.guest: chỉ khi chưa đăng nhập · meta.admin: trưởng nhóm/QTV của nhóm.
const admin = { auth: true, admin: true };
const soon = (title) => ({ component: () => import('./pages/admin/ComingSoonPage.vue'), props: { title }, meta: admin });

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', component: () => import('./pages/HomePage.vue') },
        { path: '/dang-nhap', component: () => import('./pages/auth/LoginPage.vue'), meta: { guest: true } },
        { path: '/dang-ky', component: () => import('./pages/auth/RegisterPage.vue'), meta: { guest: true } },
        { path: '/quen-mat-khau', component: () => import('./pages/auth/ForgotPasswordPage.vue'), meta: { guest: true } },
        { path: '/dat-lai-mat-khau', component: () => import('./pages/auth/ResetPasswordPage.vue'), meta: { guest: true } },
        { path: '/doi-mat-khau', component: () => import('./pages/auth/ChangePasswordPage.vue'), meta: { auth: true } },
        { path: '/nhom', component: () => import('./pages/GroupPickerPage.vue'), meta: { auth: true } },
        { path: '/ca-nhan', component: () => import('./pages/ProfilePage.vue'), meta: { auth: true } },

        { path: '/:group/quan-tri', component: () => import('./pages/admin/OverviewPage.vue'), meta: admin },
        { path: '/:group/quan-tri/danh-ba', component: () => import('./pages/admin/ContactsPage.vue'), meta: admin },
        { path: '/:group/quan-tri/danh-ba/nhap', component: () => import('./pages/admin/ContactImportPage.vue'), meta: admin },
        { path: '/:group/quan-tri/tai-khoan', component: () => import('./pages/admin/MembersPage.vue'), meta: admin },
        { path: '/:group/quan-tri/loai-thu-chi', component: () => import('./pages/admin/CategoriesPage.vue'), meta: admin },
        { path: '/:group/quan-tri/cai-dat', component: () => import('./pages/admin/SettingsPage.vue'), meta: admin },
        { path: '/:group/quan-tri/them', component: () => import('./pages/admin/MorePage.vue'), meta: admin },
        { path: '/:group/quan-tri/phieu', component: () => import('./pages/admin/TransactionsPage.vue'), meta: admin },
        { path: '/:group/quan-tri/phieu/moi', component: () => import('./pages/admin/TransactionFormPage.vue'), meta: admin },
        { path: '/:group/quan-tri/phieu/:id(\\d+)', component: () => import('./pages/admin/TransactionFormPage.vue'), meta: admin },
        { path: '/:group/quan-tri/tao-phieu', redirect: (to) => `/${to.params.group}/quan-tri/phieu/moi?loai=chi` },
        { path: '/:group/quan-tri/cho-duyet', component: () => import('./pages/admin/SepayQueuePage.vue'), meta: admin },
        { path: '/:group/quan-tri/chien-dich', component: () => import('./pages/admin/CampaignsPage.vue'), meta: admin },
        { path: '/:group/quan-tri/chien-dich/moi', component: () => import('./pages/admin/CampaignFormPage.vue'), meta: admin },
        { path: '/:group/quan-tri/chien-dich/:id(\\d+)', component: () => import('./pages/admin/CampaignDetailPage.vue'), meta: admin },
        { path: '/:group/quan-tri/chien-dich/:id(\\d+)/sua', component: () => import('./pages/admin/CampaignFormPage.vue'), meta: admin },
        { path: '/:group/quan-tri/bao-cao', component: () => import('./pages/admin/ReportsPage.vue'), meta: admin },

        { path: '/system/dang-nhap', component: () => import('./pages/system/LoginPage.vue'), meta: { system: 'guest' } },
        { path: '/system', component: () => import('./pages/system/OverviewPage.vue'), meta: { system: true } },
        { path: '/system/nhom', component: () => import('./pages/system/GroupsPage.vue'), meta: { system: true } },
        { path: '/system/nhom/moi', component: () => import('./pages/system/GroupCreatePage.vue'), meta: { system: true } },
        { path: '/system/nhom/:id(\\d+)', component: () => import('./pages/system/GroupDetailPage.vue'), meta: { system: true } },
        { path: '/system/chi-phi-ai', component: () => import('./pages/system/AiCostsPage.vue'), meta: { system: true } },
        { path: '/system/nhat-ky', component: () => import('./pages/system/AuditLogsPage.vue'), meta: { system: true } },
        { path: '/system/quan-tri-vien', component: () => import('./pages/system/AdminsPage.vue'), meta: { system: true } },
        { path: '/:group/thong-ke', component: () => import('./pages/GroupStatsPage.vue') },
        { path: '/:group/chien-dich/:slug', component: () => import('./pages/CampaignPage.vue') },
        { path: '/:group', component: () => import('./pages/GroupHomePage.vue') },
        { path: '/:pathMatch(.*)*', component: () => import('./pages/NotFoundPage.vue') },
    ],
    scrollBehavior: (to, from, saved) => saved ?? { top: 0 },
});

router.beforeEach(async (to) => {
    // Khu /system dùng phiên đăng nhập riêng, không dính tới tài khoản nhóm.
    if (to.meta.system) {
        const admin = await loadSystemMe();
        if (to.meta.system === 'guest') return admin ? '/system' : true;
        return admin ? true : { path: '/system/dang-nhap', query: { next: to.fullPath } };
    }

    await loadMe();
    const me = session.me;

    if (to.meta.guest && me) return homePath(me);
    if (to.meta.auth && !me) return { path: '/dang-nhap', query: { next: to.fullPath } };
    if (me?.must_change_password && to.path !== '/doi-mat-khau') return '/doi-mat-khau';
    if (to.meta.admin && !canManage(to.params.group)) return `/${to.params.group}`;
    return true;
});

createApp(App).use(router).mount('#app');

// PWA: đăng ký service worker (chỉ cache file tĩnh, xem public/sw.js và plan/06 mục 4).
if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
