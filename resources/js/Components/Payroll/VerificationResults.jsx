import FixList from '@/Components/Attendance/FixList';
import Collapsible from '@/Components/Console/Collapsible';
import { ConsoleButton, SecondaryButton } from '@/Components/Console/Field';
import Panel, { PanelHeading } from '@/Components/Console/Panel';
import { Tag } from '@/Components/Console/StatusBadge';
import ConfirmDialog from '@/Components/Workforce/ConfirmDialog';
import { dateTime } from '@/lib/format';
import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

function ContractorSummary({ contractor, showFixes }) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="min-w-0">
                <p className="font-medium text-console-heading">{contractor.name}</p>
                <p className="font-mono text-xs text-console-dim">{contractor.code}</p>
            </div>
            <div className="flex flex-wrap items-center gap-2">
                {showFixes && (
                    <span className="font-mono text-xs text-console-muted">
                        {contractor.fixes.length} {contractor.fixes.length === 1 ? 'change' : 'changes'}
                    </span>
                )}
                {contractor.verifiedAt ? <Tag tone="live">Submitted · {dateTime(contractor.verifiedAt)}</Tag> : <Tag tone="waiting">Not submitted yet</Tag>}
            </div>
        </div>
    );
}

/** The Admin's reminder and "submit to Super Admin" actions. */
function AdminActions({ period, counts }) {
    const { errors } = usePage().props;
    const [confirming, setConfirming] = useState(null); // 'remind' | 'submit'
    const [processing, setProcessing] = useState(false);
    const [notice, setNotice] = useState(null);

    const run = () =>
        router.post(
            route(confirming === 'remind' ? 'admin.verification.remind' : 'admin.verification.submit', period.id),
            {},
            {
                preserveScroll: true,
                onStart: () => {
                    setProcessing(true);
                    setNotice(null);
                },
                // Shown right under the buttons, not only as a toast, so it is clear the reminder went out.
                onSuccess: (page) => setNotice(page.props.flash?.success ?? null),
                onFinish: () => {
                    setProcessing(false);
                    setConfirming(null);
                },
            },
        );

    if (period.adminSubmittedAt) {
        return (
            <Tag tone="live">
                Submitted to Super Admin · {dateTime(period.adminSubmittedAt)}
                {period.adminSubmittedBy ? ` · ${period.adminSubmittedBy}` : ''}
            </Tag>
        );
    }

    if (period.status !== 'verification') {
        return null;
    }

    return (
        <div className="flex flex-col items-end gap-2">
            <div className="flex flex-wrap justify-end gap-2">
                <SecondaryButton onClick={() => setConfirming('remind')} disabled={!period.canRemind}>
                    Send reminder{counts.waiting > 0 ? ` (${counts.waiting})` : ''}
                </SecondaryButton>
                <ConsoleButton onClick={() => setConfirming('submit')} disabled={!period.canSubmit} title={period.submitBlocker ?? undefined}>
                    Submit to Super Admin
                </ConsoleButton>
            </div>
            {notice && (
                <p role="status" className="max-w-md text-right text-xs font-medium text-arka-teal">
                    {notice}
                </p>
            )}
            {period.lastRemindedAt && (
                <p className="max-w-md text-right text-xs text-console-muted">
                    Last reminder sent {dateTime(period.lastRemindedAt)} to {period.lastRemindedCount} {period.lastRemindedCount === 1 ? 'contractor' : 'contractors'}
                </p>
            )}
            {period.submitBlocker && <p className="max-w-md text-right text-xs text-console-muted">{period.submitBlocker}</p>}
            {errors.period && <p className="max-w-md text-right text-xs text-console-error">{errors.period}</p>}

            <ConfirmDialog
                open={confirming !== null}
                title={confirming === 'remind' ? 'Send a reminder?' : 'Submit the verified period?'}
                body={
                    confirming === 'remind'
                        ? `${counts.waiting} ${counts.waiting === 1 ? 'contractor has' : 'contractors have'} not submitted yet. They'll be told it's already the cut-off, to submit now, and how much time they have left to make changes.`
                        : `You reviewed the contractors' changes. The Super Admin will be notified and can process payroll. Contractors can no longer fix or submit after this.${
                              counts.waiting > 0 ? ` ${counts.waiting} who did not submit will be paid on their recorded attendance.` : ''
                          }`
                }
                confirmLabel={processing ? 'Working…' : confirming === 'remind' ? 'Send reminder' : 'Submit to Super Admin'}
                processing={processing}
                onConfirm={run}
                onClose={() => !processing && setConfirming(null)}
            />
        </div>
    );
}

/**
 * Payroll attendance verification for the most recently opened period.
 *
 * - `admin`: the Admin reviews every contractor's changes, sends reminders, and submits the verified period.
 * - `super-admin`: view-only, just who has submitted; the changes were already reviewed by the Admin.
 */
export default function VerificationResults({ verification, mode = 'super-admin' }) {
    const { period, counts, contractors } = verification;
    const isAdmin = mode === 'admin';

    if (!period) {
        return (
            <Panel className={isAdmin ? 'lg:col-span-3' : ''}>
                <PanelHeading title="Period verification" subtitle="Who confirmed their attendance for payroll" />
                <p className="mt-6 text-sm italic text-console-muted">No payroll period has been opened for verification yet.</p>
            </Panel>
        );
    }

    return (
        <Panel className={isAdmin ? 'lg:col-span-3' : ''}>
            <PanelHeading
                title="Period verification"
                subtitle={`${period.name} · opened ${dateTime(period.openedAt)} · ${period.statusLabel}`}
                action={
                    isAdmin ? (
                        <AdminActions period={period} counts={counts} />
                    ) : (
                        <Link href={route('super-admin.payroll.show', period.id)} className="text-sm text-arka-teal hover:underline">
                            Open period
                        </Link>
                    )
                }
            />

            <div className="mt-6 flex flex-wrap items-center gap-x-8 gap-y-2 text-sm">
                <p>
                    <span className="font-mono text-2xl font-medium text-console-heading">
                        {counts.verified}/{counts.total}
                    </span>{' '}
                    <span className="text-console-muted">submitted</span>
                </p>
                {isAdmin && (
                    <p>
                        <span className="font-mono text-2xl font-medium text-console-heading">{counts.fixes}</span>{' '}
                        <span className="text-console-muted">
                            {counts.fixes === 1 ? 'change' : 'changes'} by {counts.fixed} {counts.fixed === 1 ? 'contractor' : 'contractors'}
                        </span>
                    </p>
                )}
                <Tag tone={period.fixWindowOpen ? 'live' : 'closed'}>
                    {period.fixWindowOpen ? `Fixing open until ${dateTime(period.fixDeadline)}` : `Fixing closed ${dateTime(period.fixDeadline)}`}
                </Tag>
                {!isAdmin && (
                    <Tag tone={period.adminSubmittedAt ? 'live' : 'waiting'}>
                        {period.adminSubmittedAt
                            ? `Submitted by ${period.adminSubmittedBy ?? 'the Admin'} · ${dateTime(period.adminSubmittedAt)}`
                            : 'Waiting for the Admin to submit'}
                    </Tag>
                )}
            </div>

            {isAdmin ? (
                <div className="mt-6 flex flex-col gap-2">
                    {contractors.length === 0 && <p className="text-sm italic text-console-muted">No contractors are paid in this period.</p>}
                    {contractors.map((contractor) => (
                        <Collapsible key={contractor.id} summary={<ContractorSummary contractor={contractor} showFixes />}>
                            <FixList fixes={contractor.fixes} emptyMessage="No days changed. The recorded attendance was kept as is." />
                        </Collapsible>
                    ))}
                </div>
            ) : (
                <ul className="mt-6 border-t border-console-line">
                    {contractors.length === 0 && <li className="py-4 text-sm italic text-console-muted">No one has submitted yet.</li>}
                    {contractors.map((contractor) => (
                        <li key={contractor.id} className="border-b border-console-line py-3">
                            <ContractorSummary contractor={contractor} showFixes={false} />
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}
