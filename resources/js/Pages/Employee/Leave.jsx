import Dialog from '@/Components/Console/Dialog';
import Field, { ConsoleButton, SecondaryButton, SelectField, TextAreaField } from '@/Components/Console/Field';
import Panel, { Eyebrow, MetricRow, PanelHeading } from '@/Components/Console/Panel';
import StatusBadge from '@/Components/Console/StatusBadge';
import FilePicker from '@/Components/Console/FilePicker';
import ConfirmDialog from '@/Components/Workforce/ConfirmDialog';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import AppLayout from '@/Layouts/AppLayout';
import { dateRange, fullDate, timeAgo } from '@/lib/format';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const control =
    'rounded-none border border-console-line bg-console-panel py-2 pl-3 pr-9 text-sm text-console-text transition-colors hover:border-arka-aqua focus:border-arka-teal focus:ring-1 focus:ring-arka-teal';

function Segmented({ label, value, options, onChange }) {
    return (
        <div>
            <Eyebrow>{label}</Eyebrow>
            <div role="radiogroup" aria-label={label} className="mt-2 inline-flex border border-console-line">
                {options.map(([optionValue, optionLabel]) => (
                    <button
                        key={String(optionValue)}
                        type="button"
                        role="radio"
                        aria-checked={value === optionValue}
                        onClick={() => onChange(optionValue)}
                        className={`px-5 py-2 text-sm font-medium transition-colors ${value === optionValue ? 'bg-arka-teal text-white' : 'text-console-muted hover:text-arka-teal'}`}
                    >
                        {optionLabel}
                    </button>
                ))}
            </div>
        </div>
    );
}

function LeaveForm({ reasons, onCancel }) {
    const [fileKey, setFileKey] = useState(0);
    const { data, setData, post, processing, errors, reset } = useForm({
        paid: true,
        start_date: '',
        end_date: '',
        reason: '',
        client_informed: false,
        proof: null,
        notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('employee.leave.store'), {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            transform: (values) => ({ ...values, paid: values.paid ? 1 : 0, client_informed: values.client_informed ? 1 : 0 }),
            onSuccess: () => {
                reset();
                setFileKey((key) => key + 1);
            },
        });
    };

    return (
        <form onSubmit={submit} className="mt-7 grid max-w-3xl gap-6">
            <Segmented label="Leave type" value={data.paid} options={[[true, 'Paid'], [false, 'Unpaid']]} onChange={(value) => setData('paid', value)} />

            <div className="grid gap-5 sm:grid-cols-2">
                <Field id="start_date" type="date" label="Start date" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} error={errors.start_date} required />
                <Field id="end_date" type="date" label="End date" min={data.start_date || undefined} value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} error={errors.end_date} required />
            </div>

            <SelectField
                id="reason"
                label="Reason"
                value={data.reason}
                onChange={(e) => setData('reason', e.target.value)}
                options={reasons.map((reason) => ({ value: reason, label: reason }))}
                placeholder="Choose a reason"
                error={errors.reason}
                required
            />

            <Segmented label="Client informed?" value={data.client_informed} options={[[true, 'Yes'], [false, 'No']]} onChange={(value) => setData('client_informed', value)} />

            <FilePicker
                key={fileKey}
                id="proof"
                label="Proof of client confirmation (optional)"
                hint="A screenshot or email · PDF, JPG or PNG · up to 10 MB"
                accept=".pdf,.jpg,.jpeg,.png"
                file={data.proof}
                onChange={(file) => setData('proof', file)}
                error={errors.proof}
            />

            <TextAreaField id="notes" label="Anything else we should know? (optional)" value={data.notes} onChange={(e) => setData('notes', e.target.value)} error={errors.notes} />

            <div>
                <div className="flex items-center gap-4">
                    <ConsoleButton type="submit" disabled={processing}>
                        Submit leave request
                    </ConsoleButton>
                    <button type="button" onClick={onCancel} className="text-sm text-console-muted hover:text-arka-teal">
                        Cancel
                    </button>
                </div>
                <p className="mt-3 text-xs text-console-muted">
                    If you mark the client as informed but skip the proof, we'll flag it as Needs Verification for the Super Admin — nothing is rejected.
                </p>
            </div>
        </form>
    );
}

export default function Leave({ requests, filters, reasons, statuses }) {
    const [formOpen, setFormOpen] = useState(requests.length === 0);
    const [viewing, setViewing] = useState(null);
    const [cancelling, setCancelling] = useState(null);
    const [processing, setProcessing] = useState(false);
    const [range, setRange] = useState(filters);

    const filter = (next) =>
        router.get(route('employee.leave.index'), Object.fromEntries(Object.entries({ ...range, ...next }).filter(([, value]) => value !== '')), {
            preserveScroll: true,
            preserveState: true,
        });

    const cancel = () =>
        router.post(route('employee.leave.cancel', cancelling.id), {}, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setCancelling(null);
            },
        });

    return (
        <AppLayout title="Leave requests" eyebrow="Leave request">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <Panel>
                    <PanelHeading
                        title="New leave request"
                        subtitle="Tell us the dates and the reason — we'll take it from there."
                        action={!formOpen && <ConsoleButton onClick={() => setFormOpen(true)}>New leave request</ConsoleButton>}
                    />
                    {formOpen && <LeaveForm reasons={reasons} onCancel={() => setFormOpen(false)} />}
                </Panel>

                <Panel>
                    <PanelHeading
                        title="My leave requests"
                        action={
                            <div className="flex flex-wrap items-center gap-2">
                                <input type="date" aria-label="From" value={range.from} onChange={(e) => setRange({ ...range, from: e.target.value })} onBlur={() => filter({})} className={control} />
                                <input type="date" aria-label="To" value={range.to} onChange={(e) => setRange({ ...range, to: e.target.value })} onBlur={() => filter({})} className={control} />
                                <select
                                    aria-label="Filter by status"
                                    value={range.status}
                                    onChange={(e) => {
                                        setRange({ ...range, status: e.target.value });
                                        filter({ status: e.target.value });
                                    }}
                                    className={control}
                                >
                                    <option value="">All statuses</option>
                                    {statuses.map((status) => (
                                        <option key={status.value} value={status.value}>
                                            {status.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        }
                    />
                    <div className="mt-6">
                        <Table columns={['Dates', 'Type', 'Reason', 'Client informed', 'Status', 'Action']} isEmpty={requests.length === 0} emptyMessage="No leave requests yet.">
                            {requests.map((leave) => (
                                <Row key={leave.id}>
                                    <Cell className="font-mono">{dateRange(leave.startDate, leave.endDate)}</Cell>
                                    <Cell>{leave.paid ? 'Paid' : 'Unpaid'}</Cell>
                                    <Cell className="text-console-muted">{leave.reason}</Cell>
                                    <Cell>{leave.clientInformed ? (leave.hasProof ? 'Yes · proof attached' : 'Yes') : 'No'}</Cell>
                                    <Cell>
                                        <StatusBadge status={leave.status} label={leave.statusLabel} />
                                    </Cell>
                                    <td className="py-3 text-right align-top">
                                        {leave.canCancel ? (
                                            <button type="button" onClick={() => setCancelling(leave)} className="px-2 py-1 text-xs text-console-muted hover:bg-console-raised hover:text-console-heading">
                                                Cancel request
                                            </button>
                                        ) : (
                                            <button type="button" onClick={() => setViewing(leave)} className="px-2 py-1 text-xs text-arka-teal hover:bg-console-raised">
                                                View
                                            </button>
                                        )}
                                    </td>
                                </Row>
                            ))}
                        </Table>
                    </div>
                </Panel>
            </div>

            <Dialog open={viewing !== null} onClose={() => setViewing(null)} title="Leave request" description={viewing ? dateRange(viewing.startDate, viewing.endDate) : ''}>
                {viewing && (
                    <div>
                        <StatusBadge status={viewing.status} label={viewing.statusLabel} />
                        <div className="mt-5 border-t border-console-line">
                            <MetricRow label="Type" value={viewing.paid ? 'Paid' : 'Unpaid'} />
                            <MetricRow label="Reason" value={viewing.reason} />
                            <MetricRow label="Client informed" value={viewing.clientInformed ? 'Yes' : 'No'} />
                            <MetricRow label="Submitted" value={timeAgo(viewing.submittedAt)} />
                            {viewing.reviewedAt && <MetricRow label="Decided" value={fullDate(viewing.reviewedAt.slice(0, 10))} />}
                        </div>
                        {viewing.notes && <p className="mt-4 text-sm text-console-muted">“{viewing.notes}”</p>}
                        <SecondaryButton className="mt-6" onClick={() => setViewing(null)}>
                            Close
                        </SecondaryButton>
                    </div>
                )}
            </Dialog>

            <ConfirmDialog
                open={cancelling !== null}
                title="Withdraw this leave request?"
                body={cancelling ? `Your request for ${dateRange(cancelling.startDate, cancelling.endDate)} will be withdrawn. You can file a new one any time.` : ''}
                confirmLabel="Withdraw request"
                processing={processing}
                onConfirm={cancel}
                onClose={() => setCancelling(null)}
            />
        </AppLayout>
    );
}
