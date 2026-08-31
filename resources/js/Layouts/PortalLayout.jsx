import { Head, Link, router, usePage } from '@inertiajs/react';
import { ThemeToggle } from '@/Components/ui';

export default function PortalLayout({ title, children }) {
    const { auth, flash } = usePage().props;

    return (
        <div className="min-h-full">
            {title && <Head title={title} />}

            <header className="flex items-center gap-3 border-b border-line bg-surface px-4 py-3 sm:gap-4 sm:px-5">
                <Link href="/portal" className="flex items-center gap-2 text-ink no-underline">
                    <span className="grid h-9 w-9 place-items-center rounded-lg bg-accent text-xs font-bold text-accent-ink">3D</span>
                    <span>
                        <strong className="block">Agency Management</strong>
                        <small className="block text-xs text-muted max-sm:hidden">Portal klien</small>
                    </span>
                </Link>

                <div className="ml-auto flex items-center gap-2 sm:gap-3">
                    {/* Nama klien disembunyikan di ponsel: bersama tombol tema
                        dan keluar, ia membuat header meluber di layar 360px. */}
                    <span className="text-sm text-muted max-sm:hidden">{auth.client?.name}</span>
                    <ThemeToggle />
                    <button
                        type="button"
                        onClick={() => router.post('/portal/logout')}
                        className="cursor-pointer rounded-lg border border-line bg-transparent px-3 py-2 text-sm text-ink hover:border-accent"
                    >
                        Keluar
                    </button>
                </div>
            </header>

            <main className="mx-auto max-w-4xl p-4 md:p-6">
                {flash?.status && (
                    <div className="mb-4 rounded-lg border border-ok px-3 py-2 text-sm text-ok">{flash.status}</div>
                )}
                {children}
            </main>
        </div>
    );
}
