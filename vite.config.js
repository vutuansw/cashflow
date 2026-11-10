import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

// Một ứng dụng Vue 3 cho cả khu xem nhóm, quản trị nhóm và /system, xem plan/06.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                }),
                // Chữ tiêu đề landing page (plan/14). Chỉ trang chủ dùng nên không preload.
                bunny('Be Vietnam Pro', {
                    weights: [700, 800],
                    subsets: ['latin', 'vietnamese'],
                    preload: false,
                }),
            ],
        }),
        vue({
            template: { transformAssetUrls: { base: null, includeAbsolute: false } },
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
