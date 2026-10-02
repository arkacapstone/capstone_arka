import Dialog from '@/Components/Console/Dialog';
import { ConsoleButton, TextAreaField } from '@/Components/Console/Field';
import Panel, { PanelHeading } from '@/Components/Console/Panel';
import StatusBadge, { Tag } from '@/Components/Console/StatusBadge';
import { EyeIcon } from '@/Components/Icons';
import ConfirmDialog from '@/Components/Workforce/ConfirmDialog';
import RateForm from '@/Components/Workforce/RateForm';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { dateRange, fullDate, peso, timeAgo } from '@/lib/format';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const tabClass = (active) =>
    `-mb-px border-b-2 px-4 py-2 font-condensed text-[15px] font-semibold transition-colors ${
        active ? 'border-arka-teal text-arka-teal' : 'border-transparent text-console-muted hover:text-arka-teal'
    }`;

export default function Index({ type, tab, requests, assignments, overtime, pendingCount, pendingAssignments, pendingOvertime, rateDefaults, payFrequencies }) {
    return (
        <SuperAdminLayout title="Requests & Approvals">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <div className="flex flex-wrap gap-2">
                    {[
                        ['leave', `Leave requests (${pendingCount})`],
                        ['clients', `Client assignments (${pendingAssignments})`],
                        ['overtime', `Overtime (${pendingOvertime})`],
                    ].map(([value, label]) => (
                        <Link
                            key={value}
                            href={route('super-admin.requests', value === 'leave' ? {} : { type: value })}
                            preserveScroll
                            className={`border px-4 py-2 text-sm font-medium transition-colors ${
                                type === value ? 'border-arka-teal bg-arka-teal text-white' : 'border-console-line text-console-heading hover:border-arka-teal hover:text-arka-teal'
                            }`}
                        >
                            {label}
                        </Link>
                    ))}
                </div>

                {type === 'overtime' ? (
                    <OvertimeTickets tab={tab} tickets={overtime} pendingCount={pendingOvertime} />
                ) : type === 'clients' ? (
                    <ClientAssignments tab={tab} assignments={assignments} pendingCount={pendingAssignments} rateDefaults={rateDefaults} payFrequencies={payFrequencies} />
                ) : (
                    <LeaveRequests tab={tab} requests={requests} pendingCount={pendingCount} />
                )}
            </div>
        </SuperAdminLayout>
    );
}

function ClientAssignments({ tab, assignments, pendingCount, rateDefaults, payFrequencies }) {
    const [approving, setApproving] = useState(null);
    const [rejecting, setRejecting] = useState(null);
    const [note, setNote] = useState('');
    const [processing, setProcessing] = useState(false);

    const reject = () =>
        router.post(route('super-admin.requests.clients.reject', rejecting.id), { note }, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setRejecting(null);
                setNote('');
            },
        });

    return (
        <Panel>
            <PanelHeading
                title="Client assignments"
                subtitle="Admins give contractors their clients. Check that the client is right for them, then approve and set the rate — or reject. The Admin schedules it after approval."
            />

            <div className="mt-6 flex gap-1 border-b border-console-line">
                {[
                    ['pending', `Waiting (${pendingCount})`],
                    ['history', 'Decided'],
                ].map(([value, label]) => (
                    <Link key={value} href={route('super-admin.requests', { type: 'clients', ...(value === 'pending' ? {} : { tab: value }) })} preserveScroll className={tabClass(tab === value)}>
                        {label}
                    </Link>
                ))}
            </div>

            <div className="mt-2">
                <Table
                    columns={['Contractor', 'Client', 'Starts', 'Given by', 'Status', tab === 'pending' ? 'Decision' : 'Decided by']}
                    isEmpty={assignments.length === 0}
                    emptyMessage={tab === 'pending' ? 'No client assignments waiting.' : 'No decided client assignments yet.'}
                    minWidth={940}
                >
                    {assignments.map((assignment) => (
                        <Row key={assignment.id}>
                            <Cell>
                                <p className="font-medium text-console-heading">{assignment.contractor.name}</p>
                                <p className="font-mono text-xs text-console-dim">
                                    {assignment.contractor.code}
                                    {assignment.contractor.type ? ` · ${assignment.contractor.type}` : ''}
                                </p>
                                <p className="mt-1 text-xs text-console-muted">
                                    {assignment.contractor.currentClients.length > 0 ? `Current: ${assignment.contractor.currentClients.join(', ')}` : 'No clients yet'}
                                </p>
                            </Cell>
                            <Cell>
                                <p className="font-medium text-console-heading">{assignment.client.name}</p>
                                <p className="font-mono text-xs text-console-dim">
                                    {assignment.client.isNew ? 'New client — created on approval' : assignment.client.code}
                                </p>
                                {assignment.employmentTypeLabel && (
                                    <Tag tone={assignment.employmentType === 'full_time' ? 'live' : 'waiting'} className="mt-1">
                                        {assignment.employmentTypeLabel}
                                    </Tag>
                                )}
                            </Cell>
                            <Cell className="font-mono">{fullDate(assignment.startDate)}</Cell>
                            <Cell className="text-xs text-console-muted">
                                {assignment.requestedBy}
                                <span className="block font-mono text-console-dim">{timeAgo(assignment.submittedAt)}</span>
                            </Cell>
                            <Cell>
                                <Tag tone={{ pending: 'waiting', approved: 'live' }[assignment.status] ?? 'closed'}>{assignment.statusLabel}</Tag>
                                {assignment.note && <p className="mt-1 max-w-xs text-xs text-console-muted">“{assignment.note}”</p>}
                            </Cell>
                            <td className="py-3 text-right align-top">
                                {tab === 'pending' ? (
                                    <div className="flex justify-end gap-1">
                                        <button
                                            type="button"
                                            onClick={() => setApproving(assignment)}
                                            className="border border-arka-teal/40 px-3 py-1 text-xs font-medium text-arka-teal hover:bg-arka-teal/10"
                                        >
                                            Approve
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setRejecting(assignment)}
                                            className="px-3 py-1 text-xs text-console-muted hover:bg-console-raised hover:text-console-heading"
                                        >
                                            Reject
                                        </button>
                                    </div>
                                ) : (
                                    <span className="text-xs text-console-muted">
                                        {assignment.reviewer ?? '—'}
                                        {assignment.reviewedAt && <span className="block font-mono text-console-dim">{timeAgo(assignment.reviewedAt)}</span>}
                                    </span>
                                )}
                            </td>
                        </Row>
                    ))}
                </Table>
            </div>

            <Dialog
                open={approving !== null}
                onClose={() => setApproving(null)}
                side
                title="Approve client assignment"
                description={approving ? `${approving.contractor.name} → ${approving.client.name}` : ''}
            >
                {approving && (
                    <RateForm
                        key={approving.id}
                        assignment={approving}
                        defaults={rateDefaults}
                        payFrequencies={payFrequencies}
                        onDone={() => setApproving(null)}
                    />
                )}
            </Dialog>

            <Dialog
                open={rejecting !== null}
                onClose={() => setRejecting(null)}
                title="Reject this client assignment?"
                description={rejecting ? `${rejecting.contractor.name} → ${rejecting.client.name}` : ''}
            >
                <p className="text-[13px] leading-relaxed text-console-muted">Nothing is assigned or scheduled. The Admin who sent it is notified.</p>
                <TextAreaField id="note" label="Reason (optional)" value={note} onChange={(e) => setNote(e.target.value)} className="mt-5" />
                <div className="mt-6 flex items-center gap-3">
                    <button
                        type="button"
                        onClick={reject}
                        disabled={processing}
                        className="border border-console-heading bg-arka-navy px-4 py-2 text-sm font-medium text-white hover:bg-[#10244a] disabled:opacity-50"
                    >
                        Reject assignment
                    </button>
                    <button type="button" onClick={() => setRejecting(null)} className="text-[13px] text-console-muted hover:text-arka-teal">
                        Cancel
                    </button>
                </div>
            </Dialog>
        </Panel>
    );
}

function LeaveRequests({ tab, requests, pendingCount }) {
    const [decision, setDecision] = useState(null); // {type: 'approve'|'reject', leave}
    const [processing, setProcessing] = useState(false);

    const decide = () =>
        router.post(route(`super-admin.requests.leave.${decision.type}`, decision.leave.id), {}, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setDecision(null);
            },
        });

    return (
        <Panel>
            <PanelHeading
                title="Leave requests"
                subtitle="Only the Super Admin approves leave. Approved days show as paid or unpaid leave on attendance."
            />

            <div className="mt-6 flex gap-1 border-b border-console-line">
                {[
                    ['pending', `Waiting (${pendingCount})`],
                    ['history', 'Decided'],
                ].map(([value, label]) => (
                    <Link key={value} href={route('super-admin.requests', value === 'pending' ? {} : { tab: value })} preserveScroll className={tabClass(tab === value)}>
                        {label}
                    </Link>
                ))}
            </div>

            <div className="mt-2">
                <Table
                    columns={['Contractor', 'Dates', 'Type', 'Reason', 'Client informed', 'Status', tab === 'pending' ? 'Decision' : 'Decided by']}
                    isEmpty={requests.length === 0}
                    emptyMessage={tab === 'pending' ? 'No leave requests waiting.' : 'No decided requests yet.'}
                    minWidth={980}
                >
                    {requests.map((leave) => (
                        <Row key={leave.id}>
                            <Cell>
                                <p className="font-medium text-console-heading">{leave.employee.name}</p>
                                <p className="font-mono text-xs text-console-dim">
                                    {leave.employee.code} · {leave.employee.role}
                                </p>
                            </Cell>
                            <Cell>
                                <p className="font-mono">{dateRange(leave.startDate, leave.endDate)}</p>
                                <p className="font-mono text-xs text-console-muted">
                                    {leave.days} {leave.days === 1 ? 'day' : 'days'} · filed {timeAgo(leave.submittedAt)}
                                </p>
                            </Cell>
                            <Cell>{leave.paid ? 'Paid' : 'Unpaid'}</Cell>
                            <Cell className="max-w-xs text-console-muted">
                                {leave.reason}
                                {leave.notes && <p className="mt-1 text-xs text-console-dim">“{leave.notes}”</p>}
                            </Cell>
                            <Cell>
                                {leave.clientInformed ? 'Yes' : 'No'}
                                {leave.proofUrl && (
                                    <a href={leave.proofUrl} target="_blank" rel="noreferrer" className="mt-1 flex items-center gap-1 text-xs text-arka-teal hover:underline">
                                        <EyeIcon className="h-3.5 w-3.5" /> Proof
                                    </a>
                                )}
                            </Cell>
                            <Cell>
                                <StatusBadge status={leave.status} label={leave.statusLabel} />
                            </Cell>
                            <td className="py-3 text-right align-top">
                                {tab === 'pending' ? (
                                    <div className="flex justify-end gap-1">
                                        <button
                                            type="button"
                                            onClick={() => setDecision({ type: 'approve', leave })}
                                            className="border border-arka-teal/40 px-3 py-1 text-xs font-medium text-arka-teal hover:bg-arka-teal/10"
                                        >
                                            Approve
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setDecision({ type: 'reject', leave })}
                                            className="px-3 py-1 text-xs text-console-muted hover:bg-console-raised hover:text-console-heading"
                                        >
                                            Reject
                                        </button>
                                    </div>
                                ) : (
                                    <span className="text-xs text-console-muted">
                                        {leave.reviewer ?? '—'}
                                        {leave.reviewedAt && <span className="block font-mono text-console-dim">{timeAgo(leave.reviewedAt)}</span>}
                                    </span>
                                )}
                            </td>
                        </Row>
                    ))}
                </Table>
            </div>

            <ConfirmDialog
                open={decision !== null}
                title={decision?.type === 'approve' ? 'Approve this leave?' : 'Reject this leave request?'}
                body={
                    decision
                        ? decision.type === 'approve'
                            ? `${decision.leave.employee.name}'s scheduled days from ${dateRange(decision.leave.startDate, decision.leave.endDate)} will show as ${decision.leave.paid ? 'paid' : 'unpaid'} leave. They will be notified.`
                            : `${decision.leave.employee.name}'s request is closed and their attendance stays unchanged. They will be notified.`
                        : ''
                }
                confirmLabel={decision?.type === 'approve' ? 'Approve leave' : 'Reject request'}
                danger={decision?.type === 'reject'}
                processing={processing}
                onConfirm={decide}
                onClose={() => setDecision(null)}
            />
        </Panel>
    );
}

function OvertimeTickets({ tab, tickets, pendingCount }) {
    const [approving, setApproving] = useState(null);
    const [rejecting, setRejecting] = useState(null);
    const [note, setNote] = useState('');
    const [processing, setProcessing] = useState(false);
    const approveForm = useForm({});

    const approve = (e) => {
        e.preventDefault();
        approveForm.post(route('super-admin.requests.overtime.approve', approving.id), {
            preserveScroll: true,
            onSuccess: () => {
                setApproving(null);
                approveForm.reset();
            },
        });
    };

    const reject = () =>
        router.post(route('super-admin.requests.overtime.reject', rejecting.id), { note }, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setRejecting(null);
                setNote('');
            },
        });

    return (
        <Panel>
            <PanelHeading
                title="Overtime tickets"
                subtitle="Filed by contractors. Overtime is not computed from the rate — it counts only when the client handler approved it, and you enter the amount. Approved overtime goes into the next payroll for that client."
            />

            <div className="mt-6 flex gap-1 border-b border-console-line">
                {[
                    ['pending', `Waiting (${pendingCount})`],
                    ['history', 'Decided'],
                ].map(([value, label]) => (
                    <Link key={value} href={route('super-admin.requests', { type: 'overtime', ...(value === 'pending' ? {} : { tab: value }) })} preserveScroll className={tabClass(tab === value)}>
                        {label}
                    </Link>
                ))}
            </div>

            <div className="mt-2">
                <Table
                    columns={['Contractor', 'Client', 'Date', 'Time', 'Approved by (client handler)', 'Status', tab === 'pending' ? 'Decision' : 'Amount']}
                    isEmpty={tickets.length === 0}
                    emptyMessage={tab === 'pending' ? 'No overtime tickets waiting.' : 'No decided overtime tickets yet.'}
                    minWidth={1040}
                >
                    {tickets.map((ticket) => (
                        <Row key={ticket.id}>
                            <Cell>
                                <p className="font-medium text-console-heading">{ticket.contractor.name}</p>
                                <p className="font-mono text-xs text-console-dim">{ticket.contractor.code}</p>
                            </Cell>
                            <Cell>
                                {ticket.client}
                                <p className="max-w-xs text-xs text-console-muted">{ticket.reason}</p>
                            </Cell>
                            <Cell className="font-mono">{fullDate(ticket.date)}</Cell>
                            <Cell className="font-mono">{ticket.duration} h</Cell>
                            <Cell>{ticket.clientHandler}</Cell>
                            <Cell>
                                <Tag tone={{ pending: 'waiting', approved: 'live' }[ticket.status] ?? 'closed'}>{ticket.statusLabel}</Tag>
                                {ticket.note && <p className="mt-1 max-w-xs text-xs text-console-muted">“{ticket.note}”</p>}
                            </Cell>
                            <td className="py-3 text-right align-top">
                                {tab === 'pending' ? (
                                    <div className="flex justify-end gap-1">
                                        <button
                                            type="button"
                                            onClick={() => setApproving(ticket)}
                                            className="border border-arka-teal/40 px-3 py-1 text-xs font-medium text-arka-teal hover:bg-arka-teal/10"
                                        >
                                            Approve
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setRejecting(ticket)}
                                            className="px-3 py-1 text-xs text-console-muted hover:bg-console-raised hover:text-console-heading"
                                        >
                                            Reject
                                        </button>
                                    </div>
                                ) : (
                                    <span className="font-mono text-xs text-console-text">
                                        {ticket.amount !== null ? peso(ticket.amount) : '—'}
                                        <span className="block font-barlow text-console-dim">{ticket.reviewer ?? ''}</span>
                                    </span>
                                )}
                            </td>
                        </Row>
                    ))}
                </Table>
            </div>

            <Dialog
                open={approving !== null}
                onClose={() => setApproving(null)}
                title="Approve overtime"
                description={approving ? `${approving.contractor.name} · ${approving.client} · ${approving.duration} h on ${fullDate(approving.date)}` : ''}
            >
                {approving && (
                    <form onSubmit={approve} className="flex flex-col gap-5">
                        <p className="text-[13px] leading-relaxed text-console-muted">
                            Approved by the client handler <span className="text-console-text">{approving.clientHandler}</span>. Overtime is paid at the plain
                            hourly rate of this client, with no premium.
                        </p>
                        <dl className="grid grid-cols-3 border border-console-line">
                            <div className="border-r border-console-line px-4 py-3">
                                <dt className="text-[11px] uppercase tracking-[0.2em] text-console-muted">Hourly rate</dt>
                                <dd className="mt-1 font-mono text-console-text">{approving.hourlyRate !== null ? peso(approving.hourlyRate) : '—'}</dd>
                            </div>
                            <div className="border-r border-console-line px-4 py-3">
                                <dt className="text-[11px] uppercase tracking-[0.2em] text-console-muted">Overtime</dt>
                                <dd className="mt-1 font-mono text-console-text">{approving.duration} h</dd>
                            </div>
                            <div className="px-4 py-3">
                                <dt className="text-[11px] uppercase tracking-[0.2em] text-console-muted">Overtime pay</dt>
                                <dd className="mt-1 font-mono text-lg text-console-heading">{approving.overtimePay !== null ? peso(approving.overtimePay) : '—'}</dd>
                            </div>
                        </dl>
                        <p className="-mt-2 text-[11px] text-console-dim">Overtime pay = hourly rate × (overtime minutes ÷ 60).</p>
                        {approveForm.errors.status && <p className="text-xs text-console-error">{approveForm.errors.status}</p>}
                        <div className="flex items-center gap-3">
                            <ConsoleButton type="submit" disabled={approveForm.processing}>
                                Approve overtime
                            </ConsoleButton>
                            <button type="button" onClick={() => setApproving(null)} className="text-[13px] text-console-muted hover:text-arka-teal">
                                Cancel
                            </button>
                        </div>
                    </form>
                )}
            </Dialog>

            <Dialog
                open={rejecting !== null}
                onClose={() => setRejecting(null)}
                title="Reject this overtime ticket?"
                description={rejecting ? `${rejecting.contractor.name} · ${rejecting.duration} h on ${fullDate(rejecting.date)}` : ''}
            >
                <p className="text-[13px] leading-relaxed text-console-muted">Nothing is added to payroll. The contractor is notified.</p>
                <TextAreaField id="overtime_note" label="Reason (optional)" value={note} onChange={(e) => setNote(e.target.value)} className="mt-5" />
                <div className="mt-6 flex items-center gap-3">
                    <button
                        type="button"
                        onClick={reject}
                        disabled={processing}
                        className="border border-console-heading bg-arka-navy px-4 py-2 text-sm font-medium text-white hover:bg-[#10244a] disabled:opacity-50"
                    >
                        Reject ticket
                    </button>
                    <button type="button" onClick={() => setRejecting(null)} className="text-[13px] text-console-muted hover:text-arka-teal">
                        Cancel
                    </button>
                </div>
            </Dialog>
        </Panel>
    );
}
