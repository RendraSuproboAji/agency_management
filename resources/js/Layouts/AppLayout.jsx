import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const NAV = [
    { label: 'Dashboard', route: '/', match: (url) => url === '/' },
    { label: 'Request', route: '/requests', badge: true },
    { label: 'Klien', route: '/clients' },
    { label: 'Project', route: '/projects' },
    { label: 'Peralatan', route: '/equipment' },
    { label: 'Tagihan', route: '/invoices' },
    { label: 'Sesi Capture', route: '/sessions' },
];

const ADMIN_NAV = [
    { label: 'Pengguna', route: '/users' },
    { label: 'Arsip', route: '/archive' },
];

export default function AppLayout({ title, children }) {
    const { auth, flash, newRequestCount, errors } = usePage().props;
    const { url } = usePage();
    const [open, setOpen] = useState(false);

    // Inertia bernavigasi tanpa melepas layout ini, jadi tanpa penutupan
    // eksplisit panel menu tetap terbuka menutupi halaman tujuan.
    useEffect(() => router.on('navigate', () => setOpen(false)), []);

    const items = [...NAV, ...(auth.user?.is_admin ? ADMIN_NAV : [])];
    const errorList = Object.values(errors ?? {});

    return (
        <div className="min-h-full">
            {title && <Head title={title} />}

            <header className="sticky top-0 z-10 flex items-center gap-4 border-b border-line bg-surface px-5 py-3">
                <button
                    type="button"
                    className="cursor-pointer border-0 bg-transparent text-xl text-ink md:hidden"
                    onClick={() => setOpen((value) => !value)}
                    aria-label="Buka menu"
                >
                    ☰
                </button>

                <Link href="/" className="flex items-center gap-2 text-ink no-underline">
                    <span className="grid h-9 w-9 place-items-center rounded-lg bg-accent text-xs font-bold text-accent-ink">3D</span>
                    <span>
                        <strong className="block">Agency Management</strong>
                        <small className="block text-xs text-muted max-sm:hidden">Manajemen jasa immersive 3D reconstruction</small>
                    </span>
                </Link>

                <div className="ml-auto flex items-center gap-3">
                    <Link href="/profile" className="hidden text-sm text-muted hover:text-accent sm:inline">
                        {auth.user?.name} · {auth.user?.role}
                    </Link>
                    <button
                        type="button"
                        onClick={() => router.post('/logout')}
                        className="cursor-pointer rounded-lg border border-line bg-transparent px-3 py-2 text-sm text-ink hover:border-accent"
                    >
                        Keluar
                    </button>
                </div>
            </header>

            <div className="md:grid md:grid-cols-[210px_1fr]">
                <nav className={`${open ? 'flex' : 'hidden'} flex-col gap-0.5 border-line bg-surface p-3 md:flex md:min-h-[calc(100vh-57px)] md:border-r`}>
                    {items.map((item) => {
                        const active = item.match ? item.match(url) : url.startsWith(item.route);

                        return (
                            <Link
                                key={item.route}
                                href={item.route}
                                className={`flex items-center justify-between rounded-lg px-3 py-2 text-sm no-underline ${
                                    active ? 'bg-accent font-semibold text-accent-ink' : 'text-ink hover:bg-raised'
                                }`}
                            >
                                {item.label}
                                {item.badge && newRequestCount > 0 && (
                                    <span className={`rounded-full px-2 text-xs font-bold ${active ? 'bg-accent-ink text-accent' : 'bg-accent text-accent-ink'}`}>
                                        {newRequestCount}
                                    </span>
                                )}
                            </Link>
                        );
                    })}
                </nav>

                <main className="mx-auto w-full min-w-0 max-w-5xl p-4 md:p-6">
                    {flash?.status && (
                        <div className="mb-4 rounded-lg border border-ok px-3 py-2 text-sm text-ok">{flash.status}</div>
                    )}

                    {errorList.length > 0 && (
                        <div className="mb-4 rounded-lg border border-danger px-3 py-2 text-sm text-danger">
                            <ul className="list-disc pl-4">
                                {errorList.map((message, index) => <li key={index}>{message}</li>)}
                            </ul>
                        </div>
                    )}

                    {children}
                </main>
            </div>
        </div>
    );
}
