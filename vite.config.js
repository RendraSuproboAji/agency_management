import { defineConfig } from 'vite';
import { fileURLToPath, URL } from 'node:url';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    build: {
        rollupOptions: {
            output: {
                // React dan Inertia hampir tidak pernah berubah, sedangkan kode
                // aplikasi sering. Disatukan, setiap deploy membatalkan cache
                // 132 kB gzip untuk pengunjung lama; dipisah, yang kedaluwarsa
                // hanya chunk aplikasi yang beberapa kB.
                manualChunks: (id) => (
                    id.includes('/node_modules/react') ||
                    id.includes('/node_modules/scheduler') ||
                    id.includes('/node_modules/@inertiajs')
                        ? 'vendor'
                        : undefined
                ),
            },
        },
    },
});
