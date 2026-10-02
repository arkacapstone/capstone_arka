import Panel, { Eyebrow, PanelHeading } from '@/Components/Console/Panel';
import StatusBadge from '@/Components/Console/StatusBadge';
import ConfirmDialog from '@/Components/Workforce/ConfirmDialog';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import useFilters, { SearchInput } from '@/Components/Workforce/useFilters';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { fullDate, peso, timeAgo } from '@/lib/format';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

const statusTone = { approved: 'running', repaid: 'completed' };

function Figure({ label, value }) {
    return (
        <div className="border border-console-line px-5 py-4">
            <Eyebrow>{label}</Eyebrow>
            <p className="mt-2 font-mono text-2xl font-medium text-console-heading">{value}</p>
        </div>
    );
}

/**
 * Contractors file cash advances with the amount; the Super Admin only approves or rejects.
 * Once approved, repayment is deducted from that contractor's payroll automatically.
 */
export default function Index({ tab, advances, filters, summary, rules }) {
    const { search, setSearch } = useFilters('super-admin.cash-advances', filters);
    const [decision, setDecision] = useState(null); // {type: 'approve'|'reject', advance}
    const [expanded, setExpanded] = useState(null);
    const [processing, setProcessing] = useState(false);

    const decide = () =>
        router.post(route(`super-admin.cash-advances.${decision.type}`, decision.advance.id), {}, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setDecision(null);
            },
        });

    // The whole remaining balance comes off the contractor's next payslip.
    const perPayroll = (advance) => advance.remaining;
    const lastColumn = { pending: 'Decision', active: 'Next payslip', history: 'Decided by' }[tab];

    return (
        <SuperAdminLayout title="Cash Advances">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Figure label="Waiting for decision" value={summary.pending} />
                    <Figure label="Outstanding balance" value={peso(summary.outstanding)} />
                    <Figure label="Advances being repaid" value={summary.activeCount} />
                    <Figure label="Released this month" value={peso(summary.releasedThisMonth)} />
                </div>

                <Panel>
                    <PanelHeading
                        title="Cash advances"
                        subtitle={`Contractors request the amount (up to ${peso(rules.maxAmount)}, set in System & Rules); only the Super Admin approves. The money is released right away and the full amount is deducted from the contractor's very next payslip.`}
                    />

                    <div className="mt-6 flex flex-wrap items-end justify-between gap-4 border-b border-console-line">
                        <div className="flex gap-1">
                            {[
                                ['pending', `Waiting (${summary.pending})`],
                                ['active', `Repaying (${summary.activeCount})`],
                                ['history', 'History'],
                            ].map(([value, label]) => (
                                <Link
                                    key={value}
                                    href={route('super-admin.cash-advances', { ...(value === 'pending' ? {} : { tab: value }), ...(filters.search ? { search: filters.search } : {}) })}
                                    preserveScroll
                                    className={`-mb-px border-b-2 px-4 py-2 font-condensed text-[15px] font-semibold transition-colors ${
                                        tab === value ? 'border-arka-teal text-arka-teal' : 'border-transparent text-console-muted hover:text-arka-teal'
                                    }`}
                                >
                                    {label}
                                </Link>
                            ))}
                        </div>
                        <div className="mb-2 w-full max-w-sm">
                            <SearchInput value={search} onChange={setSearch} placeholder="Search by contractor name or code" label="Search cash advances" />
                        </div>
                    </div>

                    <div className="mt-2">
                        <Table
                            columns={['Contractor', 'Requested', 'Amount', 'Repaid', 'Remaining', 'Reason', 'Status', lastColumn]}
                            isEmpty={advances.length === 0}
                            emptyMessage={{ pending: 'No cash advance requests waiting.', active: 'No advances are being repaid.', history: 'No closed cash advances yet.' }[tab]}
                            minWidth={1080}
                        >
                            {advances.map((advance) => (
                                <AdvanceRow
                                    key={advance.id}
                                    advance={advance}
                                    tab={tab}
                                    perPayroll={perPayroll(advance)}
                                    expanded={expanded === advance.id}
                                    onToggle={() => setExpanded(expanded === advance.id ? null : advance.id)}
                                    onApprove={() => setDecision({ type: 'approve', advance })}
                                    onReject={() => setDecision({ type: 'reject', advance })}
                                />
                            ))}
                        </Table>
                    </div>
                </Panel>
            </div>

            <ConfirmDialog
                open={decision !== null}
                title={decision?.type === 'approve' ? 'Approve this cash advance?' : 'Reject this cash advance?'}
                body={
                    decision
                        ? decision.type === 'approve'
                            ? `${decision.advance.employee.name} receives ${peso(decision.advance.amount)} today. The full ${peso(decision.advance.amount)} is deducted from their next payslip. They will be notified.`
                            : `${decision.advance.employee.name}'s request for ${peso(decision.advance.amount)} is closed. They will be notified.`
                        : ''
                }
                confirmLabel={decision?.type === 'approve' ? 'Approve' : 'Reject request'}
                danger={decision?.type === 'reject'}
                processing={processing}
                onConfirm={decide}
                onClose={() => setDecision(null)}
            />
        </SuperAdminLayout>
    );
}

function AdvanceRow({ advance, tab, perPayroll, expanded, onToggle, onApprove, onReject }) {
    return (
        <>
            <Row>
                <Cell>
                    <p className="font-medium text-console-heading">{advance.employee.name}</p>
                    <p className="font-mono text-xs text-console-dim">{advance.employee.code}</p>
                </Cell>
                <Cell className="font-mono">
                    {fullDate(advance.requestedAt.slice(0, 10))}
                    <p className="text-xs text-console-dim">{timeAgo(advance.requestedAt)}</p>
                </Cell>
                <Cell className="font-mono">{peso(advance.amount)}</Cell>
                <Cell className="font-mono">
                    {advance.releasedDate ? peso(advance.repaid) : '—'}
                    {advance.repayments.length > 0 && (
                        <button type="button" onClick={onToggle} className="block text-xs text-arka-teal hover:underline">
                            {expanded ? 'Hide' : `${advance.repayments.length} repayment${advance.repayments.length === 1 ? '' : 's'}`}
                        </button>
                    )}
                </Cell>
                <Cell className="font-mono">{advance.releasedDate ? peso(advance.remaining) : '—'}</Cell>
                <Cell className="max-w-xs text-console-muted">{advance.reason}</Cell>
                <Cell>
                    <StatusBadge status={statusTone[advance.status] ?? advance.status} label={advance.statusLabel} />
                    {advance.releasedDate && <p className="mt-1 font-mono text-xs text-console-dim">Released {fullDate(advance.releasedDate)}</p>}
                </Cell>
                <td className="py-3 text-right align-top">
                    {tab === 'pending' && (
                        <div className="flex justify-end gap-1">
                            <button type="button" onClick={onApprove} className="border border-arka-teal/40 px-3 py-1 text-xs font-medium text-arka-teal hover:bg-arka-teal/10">
                                Approve
                            </button>
                            <button type="button" onClick={onReject} className="px-3 py-1 text-xs text-console-muted hover:bg-console-raised hover:text-console-heading">
                                Reject
                            </button>
                        </div>
                    )}
                    {tab === 'active' && (
                        <span className="font-mono text-xs text-console-text">
                            {perPayroll > 0 ? peso(perPayroll) : '—'}
                            <span className="block font-barlow text-console-dim">auto-deducted</span>
                        </span>
                    )}
                    {tab === 'history' && <span className="text-xs text-console-muted">{advance.approver ?? '—'}</span>}
                </td>
            </Row>
            {expanded && (
                <tr className="border-b border-console-line bg-console-raised/50">
                    <td colSpan={8} className="px-4 py-3">
                        <ul className="grid gap-1 text-xs">
                            {advance.repayments.map((repayment) => (
                                <li key={repayment.id} className="flex flex-wrap gap-x-6 font-mono text-console-muted">
                                    <span className="w-28">{fullDate(repayment.date)}</span>
                                    <span className="w-28 text-console-text">{peso(repayment.amount)}</span>
                                    <span>{repayment.notes ?? 'Payroll deduction'}</span>
                                </li>
                            ))}
                        </ul>
                    </td>
                </tr>
            )}
        </>
    );
}
