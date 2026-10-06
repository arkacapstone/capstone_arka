import Dialog from '@/Components/Console/Dialog';
import Field, { ConsoleButton } from '@/Components/Console/Field';
import Panel, { Eyebrow, MetricRow, PanelHeading } from '@/Components/Console/Panel';
import StatusBadge from '@/Components/Console/StatusBadge';
import ConfirmDialog from '@/Components/Workforce/ConfirmDialog';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import useFilters, { SearchInput } from '@/Components/Workforce/useFilters';
import { fullDate, peso, timeAgo } from '@/lib/format';
import { Link, router, useForm } from '@inertiajs/react';
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
 * Approve the requested amount, or (custom) a lower amount the Super Admin types in. It is released
 * today and deducted in full on that payday.
 */
function ApproveDialog({ advance, custom, onClose }) {
    const { data, setData, post, processing, errors } = useForm({ amount: custom ? '' : (advance?.requestedAmount ?? '') });

    const submit = (e) => {
        e.preventDefault();
        post(route('super-admin.cash-advances.approve', advance.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open={advance !== null} onClose={onClose} title={custom ? 'Approve a different amount' : 'Approve this cash advance?'}>
            {advance && (
                <form onSubmit={submit} className="flex flex-col gap-5">
                    <div className="border-t border-console-line">
                        <MetricRow label="Contractor" value={advance.employee.name} />
                        <MetricRow label="Requested" value={peso(advance.requestedAmount)} />
                        {advance.grossPay !== null && <MetricRow label="Gross pay this pay period" value={peso(advance.grossPay)} />}
                        {advance.payday && <MetricRow label="Deducted on payday" value={fullDate(advance.payday)} />}
                    </div>
                    {custom && (
                        <Field
                            id="amount"
                            label="Amount to release (₱)"
                            type="number"
                            min="1"
                            step="0.01"
                            max={advance.requestedAmount}
                            placeholder={`Up to ${peso(advance.requestedAmount)}`}
                            value={data.amount}
                            onChange={(e) => setData('amount', e.target.value)}
                            error={errors.amount ?? errors.status}
                            autoFocus
                            required
                        />
                    )}
                    {!custom && (errors.amount || errors.status) && <p className="text-[13px] text-console-heading">{errors.amount ?? errors.status}</p>}
                    <p className="text-[13px] text-console-muted">
                        {advance.employee.name} receives {custom ? 'this amount' : peso(advance.requestedAmount)} today. The full amount is deducted from that payday's payslip, and they
                        will be notified.
                    </p>
                    <ConsoleButton type="submit" disabled={processing}>
                        {custom ? 'Approve this amount' : `Approve ${peso(advance.requestedAmount)}`}
                    </ConsoleButton>
                </form>
            )}
        </Dialog>
    );
}

/**
 * Contractors file cash advances with the amount; the Super Admin approves (for that amount or less)
 * or rejects. Once approved, repayment is deducted from that payday's payroll automatically.
 */
export default function CashAdvanceRequests({ tab, advances, filters, summary }) {
    const { search, setSearch } = useFilters('super-admin.requests', filters);
    const [decision, setDecision] = useState(null); // {advance} being rejected
    const [approving, setApproving] = useState(null);
    const [expanded, setExpanded] = useState(null);
    const [processing, setProcessing] = useState(false);

    const reject = () =>
        router.post(route('super-admin.cash-advances.reject', decision.advance.id), {}, {
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
        <>
            <div className="flex flex-col gap-8">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Figure label="Waiting for decision" value={summary.pending} />
                    <Figure label="Outstanding balance" value={peso(summary.outstanding)} />
                    <Figure label="Advances being repaid" value={summary.activeCount} />
                    <Figure label="Released this month" value={peso(summary.releasedThisMonth)} />
                </div>

                <Panel>
                    <PanelHeading
                        title="Cash advances"
                        subtitle={`Contractors can ask once a month, any day before payday, for up to their gross pay for that pay period. You decide how much to release; it is released right away and the full amount is deducted from that payday's payslip.`}
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
                                    href={route('super-admin.requests', { type: 'cash-advances', ...(value === 'pending' ? {} : { tab: value }), ...(filters.search ? { search: filters.search } : {}) })}
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
                                    onApprove={() => setApproving({ advance, custom: false })}
                                    onChangeAmount={() => setApproving({ advance, custom: true })}
                                    onReject={() => setDecision({ advance })}
                                />
                            ))}
                        </Table>
                    </div>
                </Panel>
            </div>

            <ApproveDialog
                key={approving ? `${approving.advance.id}-${approving.custom}` : 'none'}
                advance={approving?.advance ?? null}
                custom={approving?.custom ?? false}
                onClose={() => setApproving(null)}
            />

            <ConfirmDialog
                open={decision !== null}
                title="Reject this cash advance?"
                body={decision ? `${decision.advance.employee.name}'s request for ${peso(decision.advance.amount)} is closed. They will be notified.` : ''}
                confirmLabel="Reject request"
                danger
                processing={processing}
                onConfirm={reject}
                onClose={() => setDecision(null)}
            />
        </>
    );
}

function AdvanceRow({ advance, tab, perPayroll, expanded, onToggle, onApprove, onChangeAmount, onReject }) {
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
                <Cell className="font-mono">
                    {peso(advance.amount)}
                    {advance.requestedAmount !== advance.amount && <p className="text-xs text-console-dim">asked {peso(advance.requestedAmount)}</p>}
                    {advance.payday && <p className="text-xs text-console-dim">payday {fullDate(advance.payday)}</p>}
                </Cell>
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
                    {tab === 'pending' && (
                        <button type="button" onClick={onChangeAmount} className="mt-2 text-xs text-arka-teal hover:underline">
                            Change amount
                        </button>
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
