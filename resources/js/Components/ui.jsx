import { Link } from '@inertiajs/react';

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
    void: 'danger', failed: 'danger',
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

export function Table({ head, children, empty, colSpan = 1 }) {
    const rows = Array.isArray(children) ? children : [children];
    const isEmpty = rows.filter(Boolean).length === 0;

    return (
        <div className="overflow-x-auto">
            <table className="mt-3 w-full border-collapse text-sm">
                <thead>
                    <tr>
                        {head.map((label) => (
                            <th key={label} className="border-b border-line px-2 py-2 text-left text-[0.72rem] uppercase tracking-wide text-muted">
                                {label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {isEmpty ? (
                        <tr><td colSpan={colSpan || head.length} className="px-2 py-3 text-muted">{empty}</td></tr>
                    ) : children}
                </tbody>
            </table>
        </div>
    );
}

export function Td({ className = '', children, ...props }) {
    return <td className={`border-b border-line px-2 py-2 align-top ${className}`} {...props}>{children}</td>;
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
                <div key={label}>
                    <dt className="text-[0.72rem] uppercase tracking-wide text-muted">{label}</dt>
                    <dd className="mt-0.5">{value ?? '—'}</dd>
                </div>
            ))}
        </dl>
    );
}
