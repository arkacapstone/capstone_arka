import Dialog from '@/Components/Console/Dialog';
import Field, { ConsoleButton, TextAreaField } from '@/Components/Console/Field';
import Panel, { PanelHeading } from '@/Components/Console/Panel';
import StatusBadge from '@/Components/Console/StatusBadge';
import { PlusIcon } from '@/Components/Icons';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import { fullDate, peso, timeAgo } from '@/lib/format';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const statusTone = { approved: 'running', repaid: 'completed' };

/** The contractor's own cash advances: request one, follow the decision and the balance (Blueprint §13). */
export default function CashAdvances({ advances, requestWindow }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ amount: '', reason: '' });
    const outstanding = advances.filter((advance) => advance.status === 'approved').reduce((total, advance) => total + advance.remaining, 0);

    const close = () => {
        setOpen(false);
        reset();
        clearErrors();
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('employee.cash-advances.store'), { preserveScroll: true, onSuccess: close });
    };

    return (
        <Panel>
            <PanelHeading
                title="Cash advances"
                subtitle={`You can ask once a month, any day before payday, for up to your gross pay for that pay period. Once the Super Admin approves, you get it right away, and the full amount is deducted from that payday's payslip.`}
                action={
                    <ConsoleButton onClick={() => setOpen(true)} disabled={!requestWindow.open} title={requestWindow.reason ?? undefined}>
                        <PlusIcon className="h-4 w-4" /> Request cash advance
                    </ConsoleButton>
                }
            />

            {!requestWindow.open && <p className="mt-4 text-sm text-console-muted">{requestWindow.reason}</p>}

            {outstanding > 0 && (
                <p className="mt-4 font-mono text-sm text-console-text">
                    Outstanding balance: <span className="font-medium text-console-heading">{peso(outstanding)}</span>
                </p>
            )}

            <div className="mt-6">
                <Table
                    columns={['Requested', 'Amount', 'Remaining', 'Reason', 'Status', 'Action']}
                    isEmpty={advances.length === 0}
                    emptyMessage="No cash advances yet."
                    minWidth={720}
                >
                    {advances.map((advance) => (
                        <Row key={advance.id}>
                            <Cell className="font-mono">
                                {fullDate(advance.requestedAt.slice(0, 10))}
                                <p className="text-xs text-console-dim">{timeAgo(advance.requestedAt)}</p>
                            </Cell>
                            <Cell className="font-mono">
                                {peso(advance.amount)}
                                {advance.requestedAmount !== advance.amount && <p className="text-xs text-console-dim">asked {peso(advance.requestedAmount)}</p>}
                                {advance.payday && <p className="text-xs text-console-dim">payday {fullDate(advance.payday)}</p>}
                            </Cell>
                            <Cell className="font-mono">{advance.releasedDate ? peso(advance.remaining) : '—'}</Cell>
                            <Cell className="max-w-xs text-console-muted">{advance.reason}</Cell>
                            <Cell>
                                <StatusBadge status={statusTone[advance.status] ?? advance.status} label={advance.statusLabel} />
                            </Cell>
                            <td className="py-3 text-right align-top">
                                {advance.canCancel ? (
                                    <button
                                        type="button"
                                        onClick={() => router.post(route('employee.cash-advances.cancel', advance.id), {}, { preserveScroll: true })}
                                        className="px-2 py-1 text-xs text-console-muted hover:bg-console-raised hover:text-console-heading"
                                    >
                                        Withdraw
                                    </button>
                                ) : (
                                    <span className="px-2 text-xs text-console-dim">—</span>
                                )}
                            </td>
                        </Row>
                    ))}
                </Table>
            </div>

            <Dialog
                open={open}
                onClose={close}
                side
                title="Request a cash advance"
                description={`Up to ${peso(requestWindow.limit)}. The full amount is deducted from your payslip on ${requestWindow.payday ? fullDate(requestWindow.payday) : 'payday'}. The Super Admin decides how much to release and you'll be notified.`}
            >
                <form onSubmit={submit} className="flex flex-col gap-5">
                    <Field
                        id="amount"
                        label="Amount (₱)"
                        type="number"
                        min="1"
                        step="0.01"
                        max={requestWindow.limit}
                        value={data.amount}
                        onChange={(e) => setData('amount', e.target.value)}
                        error={errors.amount}
                        required
                    />
                    <TextAreaField id="reason" label="Reason" value={data.reason} onChange={(e) => setData('reason', e.target.value)} error={errors.reason} required />
                    <ConsoleButton type="submit" disabled={processing}>
                        Send request
                    </ConsoleButton>
                </form>
            </Dialog>
        </Panel>
    );
}
