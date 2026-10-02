import { Link } from '@inertiajs/react';

/** Line-drawn "blueprint" card: square corners, transparent ground, four corner registration marks. */
export default function Panel({ className = '', children }) {
    return (
        <section
            className={`group/panel relative border border-console-line p-6 transition-colors duration-200 hover:border-console-mark sm:p-7 ${className}`}
        >
            {['-left-[5px] -top-[9px]', '-right-[5px] -top-[9px]', '-bottom-[9px] -left-[5px]', '-bottom-[9px] -right-[5px]'].map(
                (position) => (
                    <span
                        key={position}
                        aria-hidden="true"
                        className={`pointer-events-none absolute ${position} select-none font-mono text-sm leading-none text-console-mark transition-colors group-hover/panel:text-arka-teal`}
                    >
                        +
                    </span>
                ),
            )}
            {children}
        </section>
    );
}

export function PanelHeading({ title, subtitle, action }) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 className="font-condensed text-[22px] font-bold leading-tight tracking-tight text-console-heading">{title}</h2>
                {subtitle && <p className="mt-1 text-sm text-console-muted">{subtitle}</p>}
            </div>
            {action}
        </div>
    );
}

/** Label/value row separated by a hairline. Values are monospace so digits align. */
export function MetricRow({ label, value, href }) {
    const content = (
        <>
            <span>{label}</span>
            <span className="font-mono text-[13px] text-console-text">{value}</span>
        </>
    );

    const className = 'flex items-center justify-between gap-4 border-b border-console-line py-2 text-sm text-console-muted';

    return href ? (
        <Link href={href} className={`${className} transition-colors hover:bg-console-raised hover:text-console-text`}>
            {content}
        </Link>
    ) : (
        <div className={className}>{content}</div>
    );
}

/** Small uppercase label above a figure. */
export function Eyebrow({ className = '', children }) {
    return <p className={`text-[11px] font-medium uppercase tracking-[0.2em] text-console-muted ${className}`}>{children}</p>;
}

/** Full-width reversed navy field (hero bars, net pay, combined totals). */
export function ReversedBar({ className = '', children }) {
    return <section className={`bg-console-hero px-6 py-7 text-white sm:px-7 ${className}`}>{children}</section>;
}
