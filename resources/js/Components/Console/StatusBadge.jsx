/**
 * Status tags (brand guide): no red anywhere.
 *  - live:    teal solid tint  — running, active, approved, completed, present
 *  - waiting: outline          — pending, needs verification, incomplete
 *  - closed:  neutral gray     — inactive, rejected, ended, absent
 */
const tones = {
    live: 'border-arka-teal/30 bg-arka-teal/10 text-arka-teal',
    waiting: 'border-console-heading/40 bg-transparent text-console-heading',
    closed: 'border-console-line bg-console-closed text-console-muted',
};

const toneByStatus = {
    active: 'live',
    approved: 'live',
    present: 'live',
    submitted: 'live',
    running: 'live',
    completed: 'live',
    paid_leave: 'live',
    available: 'live',
    pending: 'waiting',
    invited: 'waiting',
    pending_approval: 'waiting',
    incomplete: 'waiting',
    late: 'waiting',
    undertime: 'waiting',
    not_submitted: 'waiting',
    needs_verification: 'waiting',
    on_break: 'waiting',
    processing: 'waiting',
    correction_pending: 'waiting',
    inactive: 'closed',
    rejected: 'closed',
    ended: 'closed',
    absent: 'closed',
    unpaid_leave: 'closed',
    cancelled: 'closed',
    stopped: 'closed',
    not_started: 'closed',
    shift_ended: 'closed',
};

export function Tag({ tone = 'closed', className = '', children }) {
    return (
        <span className={`inline-flex items-center gap-1.5 whitespace-nowrap border px-2 py-0.5 text-xs font-medium ${tones[tone]} ${className}`}>
            {children}
        </span>
    );
}

export default function StatusBadge({ status, label }) {
    const tone = toneByStatus[status] ?? 'closed';

    return (
        <Tag tone={tone}>
            {tone === 'live' && <span className="h-1.5 w-1.5 rounded-full bg-arka-teal" />}
            <span className="capitalize">{label ?? String(status).replaceAll('_', ' ')}</span>
        </Tag>
    );
}
