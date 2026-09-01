import { Link } from '@inertiajs/react';
import { Children, cloneElement, isValidElement, useCallback, useRef, useState } from 'react';
import { useTheme } from '@/useTheme';

const BADGE_TONES = {
    ok: 'border-ok text-ok',
    accent: 'border-accent text-accent',
    warn: 'border-warn text-warn',
    danger: 'border-danger text-danger',
    plain: 'border-line text-muted',
};

const TONE_BY_STATUS = {
    active: 'ok', approved: 'ok', delivered: 'ok', done: 'ok', admin: 'ok',
    paid: 'ok', accepted: 'ok', converted: 'ok', available: 'ok',
    capture: 'accent', processing: 'accent', submitted: 'accent',
    scheduled: 'accent', review: 'accent', running: 'accent', sent: 'accent',
    lead: 'warn', survey: 'warn', draft: 'warn', partial: 'warn', new: 'warn',
    queued: 'warn', maintenance: 'warn',
    revision: 'danger', cancelled: 'danger', rejected: 'danger',
    void: 'danger', failed: 'danger', kedaluwarsa: 'danger',
};

export function Badge({ status }) {
    const tone = BADGE_TONES[TONE_BY_STATUS[status] ?? 'plain'];

    return (
        <span className={`inline-block rounded-full border px-2 py-0.5 text-[0.7rem] uppercase tracking-wide ${tone}`}>
            {String(status).replace(/_/g, ' ')}
        </span>
    );
}

export function Money({ amount }) {
    return `Rp ${new Intl.NumberFormat('id-ID').format(Math.round(Number(amount) || 0))}`;
}


export function ThemeToggle() {
    const [theme, toggle] = useTheme();
    const toDark = theme === 'light';

    return (
        <button
            type="button"
            onClick={toggle}
            aria-label={toDark ? 'Ganti ke mode gelap' : 'Ganti ke mode terang'}
            title={toDark ? 'Mode gelap' : 'Mode terang'}
            className="cursor-pointer rounded-lg border border-line bg-transparent px-3 py-2 text-sm text-ink hover:border-accent"
        >
            {toDark ? '☾' : '☀'}
        </button>
    );
}

export function Panel({ title, actions, children }) {
    return (
        <section className="mb-4 rounded-lg border border-line bg-surface p-4">
            {(title || actions) && (
                <div className="mb-3 flex flex-wrap items-start justify-between gap-3">
                    {title && <h2 className="text-base font-semibold">{title}</h2>}
                    {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
                </div>
            )}
            {children}
        </section>
    );
}

/**
 * Tabel yang berubah menjadi kartu di layar sempit.
 *
 * Di bawah breakpoint sm, thead disembunyikan dan tiap baris menjadi kartu
 * berisi pasangan label-nilai. Labelnya diambil dari `head` dan disuntikkan ke
 * tiap sel sebagai data-label berdasarkan posisinya — aman karena tidak ada
 * satu pun baris di aplikasi ini yang jumlah selnya berbeda dari `head`.
 * Dengan begitu 16 tabel di 15 halaman ikut responsif tanpa disentuh.
 */
export function Table({ head, children, empty, colSpan = null }) {
    const rows = Children.toArray(children);
    const isEmpty = rows.length === 0;

    const labelled = rows.map((row) => (
        isValidElement(row)
            ? cloneElement(row, undefined, Children.map(row.props.children, (cell, index) => (
                isValidElement(cell) ? cloneElement(cell, { 'data-label': head[index] }) : cell
            )))
            : row
    ));

    return (
        <div className="overflow-x-auto">
            <table className="mt-3 w-full border-collapse text-sm max-sm:block">
                <thead className="max-sm:hidden">
                    <tr>
                        {head.map((label) => (
                            <th key={label} className="border-b border-line px-2 py-2 text-left text-[0.72rem] uppercase tracking-wide text-muted">
                                {label}
                            </th>
                        ))}
                    </tr>
                </thead>
                {/* Tiap baris jadi kartu berbingkai di layar sempit. */}
                <tbody className="max-sm:block max-sm:[&>tr]:mb-2 max-sm:[&>tr]:block max-sm:[&>tr]:rounded-lg max-sm:[&>tr]:border max-sm:[&>tr]:border-line max-sm:[&>tr]:py-2">
                    {isEmpty ? (
                        <tr className="max-sm:block">
                            <td colSpan={colSpan ?? head.length} className="px-2 py-3 text-muted max-sm:block">{empty}</td>
                        </tr>
                    ) : labelled}
                </tbody>
            </table>
        </div>
    );
}

export function Td({ className = '', children, ...props }) {
    return (
        <td
            className={
                // break-words wajib: email dan slug panjang tidak punya titik
                // patah alami, jadi tanpa ini teksnya terpotong di tepi sel
                // tanpa membuat halaman meluber — cacat yang lolos dari tes
                // gulir menyamping dan hanya terlihat kalau dilihat.
                'border-b border-line px-2 py-2 align-top break-words ' +
                // Kartu: label kolom muncul di kiri, nilainya di kanan.
                "max-sm:flex max-sm:justify-between max-sm:gap-3 max-sm:border-0 max-sm:px-3 max-sm:py-1 " +
                "max-sm:[&>*]:min-w-0 " +
                "max-sm:before:shrink-0 max-sm:before:text-[0.7rem] max-sm:before:uppercase max-sm:before:tracking-wide " +
                "max-sm:before:text-muted max-sm:before:content-[attr(data-label)] " +
                className
            }
            {...props}
        >
            {children}
        </td>
    );
}

const BUTTON_STYLES = {
    default: 'border-line bg-raised text-ink hover:border-accent',
    primary: 'border-accent bg-accent font-semibold text-accent-ink',
    ghost: 'border-line bg-transparent text-ink hover:border-accent',
    danger: 'border-line bg-raised text-danger hover:border-danger',
};

export function Button({ variant = 'default', small = false, className = '', ...props }) {
    const size = small ? 'px-2 py-1 text-xs' : 'px-3 py-2 text-sm';

    return <button className={`cursor-pointer rounded-lg border whitespace-nowrap ${BUTTON_STYLES[variant]} ${size} ${className}`} {...props} />;
}

/**
 * Tombol yang menanyakan konfirmasi lebih dulu.
 *
 * Menggantikan window.confirm: dialog bawaan tidak ikut mode gelap,
 * tampilannya berbeda di tiap peramban, dan di sebagian peramban bisa
 * diblokir — kalau diblokir, tombolnya diam saja tanpa jejak sama sekali.
 *
 * Memakai elemen <dialog> bawaan HTML, jadi Esc, fokus, dan latar gelapnya
 * ditangani peramban sendiri tanpa pustaka tambahan.
 */
export function ConfirmButton({
    message,
    onConfirm,
    confirmLabel = 'Ya, lanjutkan',
    variant = 'danger',
    small = false,
    className = '',
    children,
}) {
    const dialog = useRef(null);
    const [busy, setBusy] = useState(false);

    const close = useCallback(() => dialog.current?.close(), []);

    const confirm = useCallback(() => {
        setBusy(true);
        close();
        onConfirm();
    }, [close, onConfirm]);

    return (
        <>
            <Button variant={variant} small={small} className={className}
                    disabled={busy} onClick={() => dialog.current?.showModal()}>
                {children}
            </Button>

            <dialog ref={dialog}
                    className="m-auto w-[min(24rem,calc(100vw-2rem))] rounded-xl border border-line bg-raised p-4 text-ink backdrop:bg-black/50">
                <p className="text-sm">{message}</p>
                <div className="mt-4 flex justify-end gap-2">
                    <Button onClick={close}>Batal</Button>
                    <Button variant={variant} onClick={confirm}>{confirmLabel}</Button>
                </div>
            </dialog>
        </>
    );
}

export function ButtonLink({ href, variant = 'default', small = false, className = '', ...props }) {
    const size = small ? 'px-2 py-1 text-xs' : 'px-3 py-2 text-sm';

    return <Link href={href} className={`inline-block rounded-lg border no-underline whitespace-nowrap ${BUTTON_STYLES[variant]} ${size} ${className}`} {...props} />;
}

export function PageHead({ title, subtitle, children }) {
    return (
        <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 className="text-2xl font-semibold">{title}</h1>
                {subtitle && <div className="mt-1 text-sm text-muted">{subtitle}</div>}
            </div>
            {children && <div className="flex flex-wrap gap-2">{children}</div>}
        </div>
    );
}

export function Field({ label, error, children, wide = false }) {
    return (
        <label className={`mb-3 block text-xs text-muted ${wide ? 'sm:col-span-2' : ''}`}>
            {label}
            {children}
            {error && <span className="mt-1 block text-danger">{error}</span>}
        </label>
    );
}

export const inputClass =
    'mt-1 w-full rounded-lg border border-line bg-raised px-2 py-2 text-sm text-ink outline-accent focus:outline-2';

export function Pagination({ links }) {
    if (!links || links.length <= 3) {
        return null;
    }

    return (
        <nav className="mt-4 flex flex-wrap gap-1 text-sm">
            {links.map((link, index) => (
                link.url
                    ? (
                        <Link
                            key={index}
                            href={link.url}
                            className={`rounded border border-line px-2 py-1 no-underline ${link.active ? 'bg-accent text-accent-ink' : 'text-ink'}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    )
                    : <span key={index} className="rounded border border-line px-2 py-1 text-muted" dangerouslySetInnerHTML={{ __html: link.label }} />
            ))}
        </nav>
    );
}

export function DetailList({ items }) {
    return (
        <dl className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            {items.map(({ label, value }) => (
                // min-w-0 melepas lebar minimum bawaan item grid, dan
                // break-words memberi titik patah pada teks yang tidak
                // punya — tanpa keduanya satu alamat email panjang cukup
                // untuk membuat seluruh halaman menggulir menyamping.
                <div key={label} className="min-w-0">
                    <dt className="text-[0.72rem] uppercase tracking-wide text-muted">{label}</dt>
                    <dd className="mt-0.5 break-words">{value ?? '—'}</dd>
                </div>
            ))}
        </dl>
    );
}
