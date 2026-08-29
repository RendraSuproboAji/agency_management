import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';

const pages = import.meta.glob('./Pages/**/*.jsx');

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
