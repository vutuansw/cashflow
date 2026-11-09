import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
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
        { path: '/:group/quan-tri/cho-duyet', ...soon('Chờ duyệt') },
        { path: '/:group/quan-tri/chien-dich', ...soon('Chiến dịch') },
        { path: '/:group/quan-tri/bao-cao', ...soon('Báo cáo') },

        { path: '/:group/thong-ke', component: () => import('./pages/GroupStatsPage.vue') },
        { path: '/:group', component: () => import('./pages/GroupHomePage.vue') },
        { path: '/:pathMatch(.*)*', component: () => import('./pages/NotFoundPage.vue') },
    ],
    scrollBehavior: (to, from, saved) => saved ?? { top: 0 },
});

router.beforeEach(async (to) => {
    await loadMe();
    const me = session.me;

    if (to.meta.guest && me) return homePath(me);
    if (to.meta.auth && !me) return { path: '/dang-nhap', query: { next: to.fullPath } };
    if (me?.must_change_password && to.path !== '/doi-mat-khau') return '/doi-mat-khau';
    if (to.meta.admin && !canManage(to.params.group)) return `/${to.params.group}`;
    return true;
});

createApp(App).use(router).mount('#app');
