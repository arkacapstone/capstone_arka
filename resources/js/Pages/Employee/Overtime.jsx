import Field, { ConsoleButton, SelectField, TextAreaField } from '@/Components/Console/Field';
import Panel, { PanelHeading } from '@/Components/Console/Panel';
import { Tag } from '@/Components/Console/StatusBadge';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import AppLayout from '@/Layouts/AppLayout';
import { fullDate, peso, todayIso } from '@/lib/format';
import { router, useForm } from '@inertiajs/react';

const tone = { pending: 'waiting', approved: 'live' };

/**
 * Overtime is not computed: it only counts when a client handler approved it. File a ticket naming
 * them; the Super Admin approves it and the amount is added to your next payslip.
 */
export default function Overtime({ tickets, clients }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        client_id: clients[0]?.value ?? '',
        date: todayIso(),
        hours: '1',
        minutes: '0',
        client_handler: '',
        reason: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('employee.overtime.store'), { preserveScroll: true, onSuccess: () => reset('client_handler', 'reason') });
    };

    return (
        <AppLayout title="Overtime" eyebrow="Overtime">
            <div className="mx-auto grid max-w-[1560px] gap-8 xl:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">
                <Panel className="self-start">
                    <PanelHeading
                        title="File an overtime ticket"
                        subtitle="Overtime counts only when your client handler approved it. The Super Admin reviews the ticket and sets the amount."
                    />
                    {clients.length === 0 ? (
                        <p className="mt-6 text-sm text-console-muted">You have no client yet, so there is nothing to file overtime for.</p>
                    ) : (
                        <form onSubmit={submit} className="mt-6 flex flex-col gap-5">
                            <SelectField id="client_id" label="Client" value={data.client_id} onChange={(e) => setData('client_id', e.target.value)} error={errors.client_id} options={clients} />
                            <Field id="date" type="date" label="Date" max={todayIso()} value={data.date} onChange={(e) => setData('date', e.target.value)} error={errors.date} required />
                            <div className="grid grid-cols-2 gap-4">
                                <Field id="hours" type="number" min="0" max="12" label="Hours" value={data.hours} onChange={(e) => setData('hours', e.target.value)} error={errors.hours} required />
                                <Field id="minutes" type="number" min="0" max="59" label="Minutes" value={data.minutes} onChange={(e) => setData('minutes', e.target.value)} error={errors.minutes} required />
                            </div>
                            <Field
                                id="client_handler"
                                label="Approved by (client handler)"
                                value={data.client_handler}
                                onChange={(e) => setData('client_handler', e.target.value)}
                                error={errors.client_handler}
                                placeholder="Full name"
                                required
                            />
                            <TextAreaField id="reason" label="What was the overtime for?" value={data.reason} onChange={(e) => setData('reason', e.target.value)} error={errors.reason} required />
                            <ConsoleButton type="submit" disabled={processing}>
                                Send ticket
                            </ConsoleButton>
                        </form>
                    )}
                </Panel>

                <Panel>
                    <PanelHeading title="My overtime tickets" subtitle="Approved overtime is added to your next payslip for that client." />
                    <div className="mt-6">
                        <Table
                            columns={['Date', 'Client', 'Time', 'Approved by', 'Status', 'Amount', 'Action']}
                            isEmpty={tickets.length === 0}
                            emptyMessage="No overtime tickets yet."
                            minWidth={820}
                        >
                            {tickets.map((ticket) => (
                                <Row key={ticket.id}>
                                    <Cell className="font-mono">{fullDate(ticket.date)}</Cell>
                                    <Cell>
                                        {ticket.client}
                                        <p className="max-w-xs text-xs text-console-muted">{ticket.reason}</p>
                                    </Cell>
                                    <Cell className="font-mono">{ticket.duration} h</Cell>
                                    <Cell>{ticket.clientHandler}</Cell>
                                    <Cell>
                                        <Tag tone={tone[ticket.status] ?? 'closed'}>{ticket.statusLabel}</Tag>
                                        {ticket.note && <p className="mt-1 max-w-xs text-xs text-console-muted">“{ticket.note}”</p>}
                                    </Cell>
                                    <Cell className="font-mono">{ticket.amount !== null ? peso(ticket.amount) : '—'}</Cell>
                                    <td className="py-3 text-right align-top">
                                        {ticket.status === 'pending' ? (
                                            <button
                                                type="button"
                                                onClick={() => router.post(route('employee.overtime.cancel', ticket.id), {}, { preserveScroll: true })}
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
                </Panel>
            </div>
        </AppLayout>
    );
}
