import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';

const pages = import.meta.glob('./Pages/**/*.jsx');

/**
 * Sesi yang berakhir mengembalikan 419. Tanpa penanganan, Inertia memunculkan
 * modal berisi halaman error mentah — membingungkan, dan isian yang sedang
 * diketik hilang tanpa penjelasan. Di sini pengguna diberi tahu apa yang
 * terjadi dan diberi pilihan memuat ulang.
 */
router.on('invalid', (event) => {
    if (event.detail.response?.status !== 419) {
        return;
    }

    event.preventDefault();

    if (window.confirm('Sesi Anda sudah berakhir karena halaman ini terbuka terlalu lama.\n\nMuat ulang halaman untuk masuk kembali?')) {
        window.location.reload();
    }
});

createInertiaApp({
    title: (title) => (title ? `${title} · ${import.meta.env.VITE_APP_NAME ?? 'Agency Management'}` : 'Agency Management'),
    resolve: async (name) => {
        const page = pages[`./Pages/${name}.jsx`];

        if (!page) {
            throw new Error(`Halaman Inertia tidak ditemukan: ${name}`);
        }

        return (await page()).default;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#f60' },
});
