import FixList from '@/Components/Attendance/FixList';
import Collapsible from '@/Components/Console/Collapsible';
import Panel, { PanelHeading } from '@/Components/Console/Panel';
import { Tag } from '@/Components/Console/StatusBadge';
import { dateTime } from '@/lib/format';
import { Link } from '@inertiajs/react';

/**
 * Payroll → Payroll verified: who submitted their attendance as verified for the most recently
 * opened period, and every day each contractor fixed, with the time it replaced.
 */
export default function VerificationResults({ verification }) {
    const { period, counts, contractors } = verification;

    if (!period) {
        return (
            <Panel>
                <PanelHeading title="Attendance verification" subtitle="Who confirmed their attendance for payroll, and what they fixed" />
                <p className="mt-6 text-sm italic text-console-muted">No payroll period has been opened for verification yet.</p>
            </Panel>
        );
    }

    return (
        <Panel>
            <PanelHeading
                title="Attendance verification"
                subtitle={`${period.name} · opened ${dateTime(period.openedAt)} · ${period.statusLabel}`}
                action={
                    <Link href={route('super-admin.payroll.show', period.id)} className="text-sm text-arka-teal hover:underline">
                        Open period
                    </Link>
                }
            />

            <div className="mt-6 flex flex-wrap items-center gap-x-8 gap-y-2 text-sm">
                <p>
                    <span className="font-mono text-2xl font-medium text-console-heading">
                        {counts.verified}/{counts.total}
                    </span>{' '}
                    <span className="text-console-muted">verified</span>
                </p>
                <p>
                    <span className="font-mono text-2xl font-medium text-console-heading">{counts.fixes}</span>{' '}
                    <span className="text-console-muted">
                        {counts.fixes === 1 ? 'fix' : 'fixes'} by {counts.fixed} {counts.fixed === 1 ? 'contractor' : 'contractors'}
                    </span>
                </p>
                <Tag tone={period.fixWindowOpen ? 'live' : 'closed'}>
                    {period.fixWindowOpen ? `Fixing open until ${dateTime(period.fixDeadline)}` : `Fixing closed ${dateTime(period.fixDeadline)}`}
                </Tag>
            </div>

            <div className="mt-6 flex flex-col gap-2">
                {contractors.length === 0 && <p className="text-sm italic text-console-muted">No contractors are paid in this period.</p>}
                {contractors.map((contractor) => (
                    <Collapsible
                        key={contractor.id}
                        summary={
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="font-medium text-console-heading">{contractor.name}</p>
                                    <p className="font-mono text-xs text-console-dim">{contractor.code}</p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-mono text-xs text-console-muted">
                                        {contractor.fixes.length} {contractor.fixes.length === 1 ? 'fix' : 'fixes'}
                                    </span>
                                    {contractor.verifiedAt ? (
                                        <Tag tone="live">Verified · {dateTime(contractor.verifiedAt)}</Tag>
                                    ) : (
                                        <Tag tone="waiting">Not verified yet</Tag>
                                    )}
                                </div>
                            </div>
                        }
                    >
                        <FixList fixes={contractor.fixes} emptyMessage="No days fixed. The recorded attendance was kept as is." />
                    </Collapsible>
                ))}
            </div>
        </Panel>
    );
}
