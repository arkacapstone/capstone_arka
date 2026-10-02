import { Link } from '@inertiajs/react';

/** "Showing 1–8 of 142" with Previous / Next, for a Laravel paginator's `meta`. */
export default function Pagination({ meta }) {
    if (!meta) return null;

    if (meta.last_page <= 1) {
        return (
            <p className="font-mono text-xs text-console-dim">
                {meta.total} record{meta.total === 1 ? '' : 's'}
            </p>
        );
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-4">
            <p className="font-mono text-xs text-console-dim">
                Showing {meta.from}–{meta.to} of {meta.total}
            </p>
            <nav className="flex flex-wrap gap-1" aria-label="Pagination">
                {meta.links.map((link, index) => {
                    const label = link.label.replace('&laquo; ', '').replace(' &raquo;', '');
                    const className = `min-w-8 border px-2.5 py-1 text-center text-xs transition-colors ${
                        link.active
                            ? 'border-arka-teal bg-arka-teal text-white'
                            : 'border-console-line text-console-muted hover:border-arka-teal hover:text-arka-teal'
                    }`;

                    return link.url ? (
                        <Link key={index} href={link.url} preserveScroll preserveState className={className}>
                            {label}
                        </Link>
                    ) : (
                        <span key={index} className={`${className} opacity-40`}>
                            {label}
                        </span>
                    );
                })}
            </nav>
        </div>
    );
}
