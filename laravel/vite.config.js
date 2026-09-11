import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5174,
        strictPort: true,
        // Docker 内の bind アドレスではなく、ホスト側から届く URL を hot ファイルに書く
        origin: 'http://localhost:5174',
        allowedHosts: true,
        cors: {
            origin: true,
        },
        hmr: {
            // host は固定しない。ブラウザの hostname を使うので LAN IP でも HMR が繋がる
            clientPort: 5174,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
            usePolling: true,
        },
    },
});
