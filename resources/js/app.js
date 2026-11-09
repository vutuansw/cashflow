import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';

// Khung route của SPA. Các khu xem nhóm, quản trị nhóm và /system thêm dần theo mốc, xem plan/06.
const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', component: () => import('./pages/HomePage.vue') },
        { path: '/:pathMatch(.*)*', component: () => import('./pages/NotFoundPage.vue') },
    ],
    scrollBehavior: (to, from, saved) => saved ?? { top: 0 },
});

createApp(App).use(router).mount('#app');
