import Dialog from '@/Components/Console/Dialog';
import Field, { ConsoleButton, SecondaryButton, TextAreaField } from '@/Components/Console/Field';
import Pagination from '@/Components/Console/Pagination';
import Panel, { Eyebrow, MetricRow, PanelHeading } from '@/Components/Console/Panel';
import StatusBadge from '@/Components/Console/StatusBadge';
import StatTile from '@/Components/Workforce/StatTile';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import useFilters from '@/Components/Workforce/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import { fullDate, timeAgo } from '@/lib/format';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

const control =
    'rounded-none border border-console-line bg-console-panel py-2 pl-3 pr-9 text-sm text-console-text transition-colors hover:border-arka-aqua focus:border-arka-teal focus:ring-1 focus:ring-arka-teal';

function FixForm({ record, onDone }) {
    const { data, setData, post, processing, errors } = useForm({
        attendance_id: record.id,
        employee_id: record.employee.id,
        date: record.date,
        time_in: record.timeIn ?? '',
        time_out: record.timeOut ?? '',
        reason: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.attendance.correct'), {
            preserveScroll: true,
            transform: (values) => ({ ...values, time_in: values.time_in || null, time_out: values.time_out || null }),
            onSuccess: onDone,
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-5">
            <div className="border border-console-line bg-console-raised px-4 py-3 text-sm">
                <p className="font-medium text-console-heading">
                    {record.employee.name} · {fullDate(record.date)}
                </p>
                <p className="mt-1 font-mono text-xs text-console-muted">
                    Scheduled {record.scheduled ?? '—'} · Recorded {record.timeInLabel ?? '—'} – {record.timeOutLabel ?? '—'}
                </p>
            </div>

            <div className="grid grid-cols-2 gap-4">
                <Field id="time_in" type="time" label="Corrected time in" value={data.time_in} onChange={(e) => setData('time_in', e.target.value)} error={errors.time_in} />
                <Field id="time_out" type="time" label="Corrected time out" value={data.time_out} onChange={(e) => setData('time_out', e.target.value)} error={errors.time_out} />
            </div>
            <p className="-mt-2 text-xs text-console-muted">A time out earlier than the time in counts as the next day (overnight shift).</p>

            <TextAreaField
                id="reason"
                label="Reason for correction"
                value={data.reason}
                onChange={(e) => setData('reason', e.target.value)}
                error={errors.reason}
                placeholder="e.g. Contractor forgot to clock out; confirmed with activity log."
                required
            />

            <p className="text-xs text-console-muted">
                The original value stays on the correction log with your name and the time. Late, undertime and status are recalculated against the schedule in effect
                that day.
            </p>

            <div className="flex items-center gap-3">
                <ConsoleButton disabled={processing}>Save correction</ConsoleButton>
                <button type="button" onClick={onDone} className="text-sm text-console-muted hover:text-arka-teal">
                    Cancel
                </button>
            </div>
        </form>
    );
}

function Details({ record, onFix }) {
    return (
        <div className="flex flex-col gap-5">
            <div className="border-t border-console-line">
                <MetricRow label="Contractor" value={record.employee.name} />
                <MetricRow label="Date" value={fullDate(record.date)} />
                <MetricRow label="Client" value={record.client ?? '—'} />
                <MetricRow label="Scheduled time" value={record.scheduled ?? 'No schedule'} />
                <MetricRow label="Actual time in" value={record.timeInLabel ?? '—'} />
                <MetricRow label="Actual time out" value={record.timeOutLabel ?? '—'} />
                <MetricRow label="Total hours" value={record.hours ?? '—'} />
                <MetricRow label="Late" value={`${record.lateMinutes} min`} />
                <MetricRow label="Undertime" value={`${record.undertimeMinutes} min`} />
                <MetricRow label="Status" value={record.statusLabel} />
                <MetricRow label="Corrections" value={record.corrections} />
            </div>
            {record.locked ? (
                <p className="text-sm text-console-muted">This day is locked for payroll, so it can no longer be corrected.</p>
            ) : (
                <ConsoleButton type="button" onClick={onFix}>
                    Fix this
                </ConsoleButton>
            )}
        </div>
    );
}

function RequestRow({ request }) {
    const [mode, setMode] = useState(null); // 'approve' | 'reject'
    const { data, setData, post, processing, errors, reset } = useForm({ remarks: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route(mode === 'approve' ? 'admin.attendance.requests.approve' : 'admin.attendance.requests.reject', request.id), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setMode(null);
            },
        });
    };

    return (
        <Row>
            <Cell className="font-mono text-xs">{fullDate(request.date)}</Cell>
            <Cell className="font-medium text-console-heading">{request.employee}</Cell>
            <Cell>{request.field}</Cell>
            <Cell className="font-mono text-xs">
                <span className="text-console-dim">{request.original}</span> → {request.requested}
            </Cell>
            <Cell className="max-w-xs text-sm">
                {request.reason}
                {request.proofUrl && (
                    <a href={request.proofUrl} target="_blank" rel="noreferrer" className="mt-1 block text-xs text-arka-teal hover:underline">
                        View proof
                    </a>
                )}
            </Cell>
            <td className="py-3 align-top">
                {mode === null ? (
                    <div className="flex justify-end gap-2">
                        <ConsoleButton type="button" onClick={() => setMode('approve')} className="!px-3 !py-1.5 !text-xs">
                            Approve
                        </ConsoleButton>
                        <SecondaryButton onClick={() => setMode('reject')} className="!px-3 !py-1.5 !text-xs">
                            Close
                        </SecondaryButton>
                    </div>
                ) : (
                    <form onSubmit={submit} className="ml-auto flex w-64 flex-col gap-2">
                        <textarea
                            value={data.remarks}
                            onChange={(e) => setData('remarks', e.target.value)}
                            rows={2}
                            placeholder={mode === 'approve' ? 'Remarks (optional)' : 'What should the contractor know? (required)'}
                            className={`${control} w-full text-xs`}
                        />
                        {errors.remarks && <p className="text-xs text-console-error">{errors.remarks}</p>}
                        {errors.status && <p className="text-xs text-console-error">{errors.status}</p>}
                        <div className="flex gap-2">
                            <ConsoleButton disabled={processing} className="!px-3 !py-1.5 !text-xs">
                                {mode === 'approve' ? 'Apply correction' : 'Close request'}
                            </ConsoleButton>
                            <button type="button" onClick={() => setMode(null)} className="text-xs text-console-muted hover:text-arka-teal">
                                Back
                            </button>
                        </div>
                    </form>
                )}
            </td>
        </Row>
    );
}

export default function Index({ filters, summary, records, requests, log, fix, employees, statuses }) {
    const { apply } = useFilters('admin.attendance.index', filters);
    const [details, setDetails] = useState(null);
    const [fixing, setFixing] = useState(fix);
    const tab = filters.tab;

    const tabs = [
        ['records', 'Attendance'],
        ['requests', `Incoming requests${requests.length ? ` (${requests.length})` : ''}`],
        ['log', 'Correction log'],
    ];

    return (
        <AppLayout title="Attendance" eyebrow="Attendance management">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <div className="grid grid-cols-2 gap-4 md:grid-cols-5">
                    <StatTile label="Present" value={summary.present} active={filters.status === 'present'} onClick={() => apply({ status: 'present', tab: 'records' })} />
                    <StatTile label="Late" value={summary.late} active={filters.status === 'late'} onClick={() => apply({ status: 'late', tab: 'records' })} />
                    <StatTile label="Absent" value={summary.absent} active={filters.status === 'absent'} onClick={() => apply({ status: 'absent', tab: 'records' })} />
                    <StatTile label="On leave" value={summary.onLeave} active={filters.status === 'paid_leave'} onClick={() => apply({ status: 'paid_leave', tab: 'records' })} />
                    <StatTile label="Incomplete" value={summary.incomplete} active={filters.status === 'incomplete'} onClick={() => apply({ status: 'incomplete', tab: 'records' })} />
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <label className="flex flex-col gap-1">
                        <Eyebrow>From</Eyebrow>
                        <input type="date" value={filters.from} onChange={(e) => apply({ from: e.target.value })} className={control} />
                    </label>
                    <label className="flex flex-col gap-1">
                        <Eyebrow>To</Eyebrow>
                        <input type="date" value={filters.to} onChange={(e) => apply({ to: e.target.value })} className={control} />
                    </label>
                    <label className="flex flex-col gap-1">
                        <Eyebrow>Contractor</Eyebrow>
                        <select value={filters.employee} onChange={(e) => apply({ employee: e.target.value })} className={control}>
                            <option value="">All contractors</option>
                            {employees.map((employee) => (
                                <option key={employee.value} value={employee.value}>
                                    {employee.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="flex flex-col gap-1">
                        <Eyebrow>Status</Eyebrow>
                        <select value={filters.status} onChange={(e) => apply({ status: e.target.value })} className={control}>
                            <option value="">All statuses</option>
                            {statuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <Link href={route('admin.attendance.index')} className="pb-2 text-sm text-console-muted hover:text-arka-teal">
                        Reset
                    </Link>
                </div>

                <nav className="flex gap-6 border-b border-console-line" aria-label="Attendance sections">
                    {tabs.map(([key, label]) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => apply({ tab: key })}
                            className={`-mb-px border-b-2 pb-3 font-condensed text-[17px] font-semibold transition-colors ${
                                tab === key ? 'border-arka-teal text-arka-teal' : 'border-transparent text-console-muted hover:text-arka-teal'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </nav>

                {tab === 'records' && (
                    <Panel>
                        <PanelHeading title="Attendance list" subtitle={`${fullDate(filters.from)} – ${fullDate(filters.to)}`} />
                        <div className="mt-6">
                            <Table
                                columns={['Contractor', 'Date', 'Client', 'Time in', 'Time out', 'Hours', 'Status', 'Actions']}
                                isEmpty={records.data.length === 0}
                                emptyMessage="No attendance records for these filters."
                                minWidth={960}
                            >
                                {records.data.map((record) => (
                                    <Row key={record.id}>
                                        <Cell>
                                            <p className="font-medium text-console-heading">{record.employee.name}</p>
                                            <p className="font-mono text-xs text-console-dim">{record.employee.code}</p>
                                        </Cell>
                                        <Cell className="font-mono text-xs">{fullDate(record.date)}</Cell>
                                        <Cell>{record.client ?? '—'}</Cell>
                                        <Cell className="font-mono text-xs">{record.timeInLabel ?? '—'}</Cell>
                                        <Cell className="font-mono text-xs">{record.timeOutLabel ?? '—'}</Cell>
                                        <Cell className="font-mono text-xs">{record.hours ?? '—'}</Cell>
                                        <Cell>
                                            <StatusBadge status={record.status} label={record.statusLabel} />
                                            {record.corrections > 0 && <p className="mt-1 text-[11px] text-console-dim">Corrected</p>}
                                        </Cell>
                                        <td className="py-3 align-top">
                                            <div className="flex justify-end gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() => setDetails(record)}
                                                    className="px-2 py-1 text-xs text-console-muted transition-colors hover:bg-console-raised hover:text-arka-teal"
                                                >
                                                    View
                                                </button>
                                                {!record.locked && (
                                                    <button
                                                        type="button"
                                                        onClick={() => setFixing(record)}
                                                        className="px-2 py-1 text-xs font-medium text-arka-teal transition-colors hover:bg-console-raised"
                                                    >
                                                        Fix this
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </Row>
                                ))}
                            </Table>
                        </div>
                        <div className="mt-5">
                            <Pagination meta={records.meta} />
                        </div>
                    </Panel>
                )}

                {tab === 'requests' && (
                    <Panel>
                        <PanelHeading
                            title="Incoming correction requests"
                            subtitle="Submitted by contractors with proof. Approving applies the correction to the official attendance before the cutoff."
                        />
                        <div className="mt-6">
                            <Table
                                columns={['Date', 'Contractor', 'Field', 'Requested change', 'Reason', 'Decision']}
                                isEmpty={requests.length === 0}
                                emptyMessage="No requests are waiting. New ones appear here as contractors submit them."
                                minWidth={960}
                            >
                                {requests.map((request) => (
                                    <RequestRow key={request.id} request={request} />
                                ))}
                            </Table>
                        </div>
                    </Panel>
                )}

                {tab === 'log' && (
                    <Panel>
                        <PanelHeading title="Correction log" subtitle="Every correction with its original value, so nothing is silently overwritten." />
                        <div className="mt-6">
                            <Table
                                columns={['Date', 'Contractor', 'Field', 'Original', 'Corrected', 'Reason', 'By', 'When', 'Result']}
                                actions={false}
                                isEmpty={log.length === 0}
                                emptyMessage="No corrections yet."
                                minWidth={1100}
                            >
                                {log.map((entry) => (
                                    <Row key={entry.id}>
                                        <Cell className="font-mono text-xs">{fullDate(entry.date)}</Cell>
                                        <Cell className="font-medium text-console-heading">{entry.employee}</Cell>
                                        <Cell>{entry.field}</Cell>
                                        <Cell className="font-mono text-xs text-console-dim">{entry.original}</Cell>
                                        <Cell className="font-mono text-xs">{entry.status === 'approved' ? entry.requested : '—'}</Cell>
                                        <Cell className="max-w-xs text-sm">
                                            {entry.reason}
                                            {entry.remarks && <p className="mt-1 text-xs text-console-muted">Admin: {entry.remarks}</p>}
                                        </Cell>
                                        <Cell>{entry.reviewer ?? '—'}</Cell>
                                        <Cell className="font-mono text-xs">{entry.reviewedAt ? timeAgo(entry.reviewedAt) : '—'}</Cell>
                                        <Cell>
                                            <StatusBadge status={entry.status} label={entry.source === 'admin' ? 'Admin fix' : undefined} />
                                        </Cell>
                                    </Row>
                                ))}
                            </Table>
                        </div>
                    </Panel>
                )}
            </div>

            <Dialog open={details !== null} onClose={() => setDetails(null)} side title="Attendance details" description={details ? fullDate(details.date) : ''}>
                {details && (
                    <Details
                        record={details}
                        onFix={() => {
                            setFixing(details);
                            setDetails(null);
                        }}
                    />
                )}
            </Dialog>

            <Dialog open={fixing !== null} onClose={() => setFixing(null)} side title="Correct attendance" description="Admin-initiated correction">
                {fixing && <FixForm key={fixing.id} record={fixing} onDone={() => setFixing(null)} />}
            </Dialog>
        </AppLayout>
    );
}
