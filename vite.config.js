import { defineConfig } from 'vite';
import { fileURLToPath, URL } from 'node:url';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            // Preact lewat lapisan compat-nya. react-dom sendiri 96 kB gzip
            // dari total 130, dan aplikasi ini hanya memakai useState serta
            // useForm — kemampuan React 19 selebihnya tidak terpakai sama
            // sekali. Kode halaman tetap ditulis sebagai React biasa.
            'react': 'preact/compat',
            'react-dom': 'preact/compat',
            'react-dom/test-utils': 'preact/test-utils',
            'react/jsx-runtime': 'preact/jsx-runtime',
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
                    id.includes('/node_modules/preact') ||
                    id.includes('/node_modules/@inertiajs')
                        ? 'vendor'
                        : undefined
                ),
            },
        },
    },
});
