import FixList from '@/Components/Attendance/FixList';
import Dialog from '@/Components/Console/Dialog';
import Field, { ConsoleButton, TextAreaField } from '@/Components/Console/Field';
import Panel, { Eyebrow, PanelHeading } from '@/Components/Console/Panel';
import StatusBadge, { Tag } from '@/Components/Console/StatusBadge';
import { CalendarDaysIcon, ChevronLeftIcon, ChevronRightIcon, ListIcon } from '@/Components/Icons';
import ConfirmDialog from '@/Components/Workforce/ConfirmDialog';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import AppLayout from '@/Layouts/AppLayout';
import { dateTime, fullDate, monthLabel, parseDate, shiftMonth, todayIso } from '@/lib/format';
import { router, useForm } from '@inertiajs/react';
import { Fragment, useMemo, useState } from 'react';

const control =
    'rounded-none border border-console-line bg-console-panel py-2 pl-3 pr-9 text-sm text-console-text transition-colors hover:border-arka-aqua focus:border-arka-teal focus:ring-1 focus:ring-arka-teal';

function VerificationFixForm({ periodId, record, onDone }) {
    const { data, setData, post, processing, errors } = useForm({
        attendance_id: record.id,
        time_in: record.timeIn ?? '',
        time_out: record.timeOut ?? '',
        reason: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('employee.attendance.verification.fix', periodId), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-5">
            <div className="border border-console-line bg-console-raised px-4 py-3 text-sm">
                <p className="font-medium text-console-heading">
                    {fullDate(record.date)} · {record.client}
                </p>
                <p className="mt-1 font-mono text-xs text-console-muted">
                    Recorded: {record.timeInLabel ?? '—'} – {record.timeOutLabel ?? '—'}
                </p>
            </div>

            <div className="grid grid-cols-2 gap-4">
                <Field id="verify_time_in" type="time" label="Correct time in" value={data.time_in} onChange={(e) => setData('time_in', e.target.value)} error={errors.time_in} />
                <Field
                    id="verify_time_out"
                    type="time"
                    label="Correct time out"
                    value={data.time_out}
                    onChange={(e) => setData('time_out', e.target.value)}
                    error={errors.time_out}
                />
            </div>

            <TextAreaField
                id="verify_reason"
                label="Reason"
                value={data.reason}
                onChange={(e) => setData('reason', e.target.value)}
                error={errors.reason}
                placeholder="e.g. Forgot to stop the timer after my shift."
                required
            />

            <ConsoleButton type="submit" disabled={processing}>
                Save fix
            </ConsoleButton>
            <p className="text-xs text-console-muted">Saved right away. Your old time is kept, so the Super Admin can see what changed.</p>
        </form>
    );
}

function VerificationPanel({ verification }) {
    const [fixing, setFixing] = useState(null);
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);
    const { periodId, name, cutoffDate, verifiedAt, canFix: windowOpen, closed, fixDeadline, fixes, records } = verification;
    const canFix = !verifiedAt && windowOpen;
    const [expanded, setExpanded] = useState([]);
    const toggle = (id) => setExpanded((ids) => (ids.includes(id) ? ids.filter((value) => value !== id) : [...ids, id]));
    // Each fix sits under the attendance row it changed.
    const fixesByRecord = fixes.reduce((map, fix) => ({ ...map, [fix.attendanceId]: [...(map[fix.attendanceId] ?? []), fix] }), {});

    const submit = () =>
        router.post(
            route('employee.attendance.verification.submit', periodId),
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setConfirming(false);
                },
            },
        );

    return (
        <Panel>
            <PanelHeading
                title="Verify your attendance"
                subtitle={`Payroll ${name} · submit before the ${fullDate(cutoffDate)} cutoff`}
                action={
                    verifiedAt ? (
                        <StatusBadge status="approved" label="Verified" />
                    ) : closed ? (
                        <Tag tone="closed">Submitted by the Admin</Tag>
                    ) : (
                        <ConsoleButton onClick={() => setConfirming(true)}>Submit as verified</ConsoleButton>
                    )
                }
            />

            <p className="mt-4 text-sm text-console-muted">
                {verifiedAt
                    ? `You submitted your attendance as verified on ${fullDate(verifiedAt.slice(0, 10))}.`
                    : canFix
                      ? `Check your time in and time out for each day. You can fix as many days as you need until ${dateTime(fixDeadline)}; each fix is saved right away. When everything is correct, submit your attendance as verified.`
                      : `Fixing closed at ${dateTime(fixDeadline)}. If everything is correct, submit your attendance as verified; for anything else, contact an Admin.`}
            </p>

            <div className="mt-6">
                <Table
                    columns={['Date', 'Client', 'Time in', 'Time out', 'Hours', 'Status', 'Action']}
                    isEmpty={records.length === 0}
                    emptyMessage="No attendance recorded in this period yet."
                >
                    {records.map((record) => {
                        const recordFixes = fixesByRecord[record.id] ?? [];
                        const open = expanded.includes(record.id);

                        return (
                            <Fragment key={record.id}>
                                <Row>
                                    <Cell className="font-mono">
                                        <div className="flex items-center gap-1.5">
                                            {recordFixes.length > 0 ? (
                                                <button
                                                    type="button"
                                                    onClick={() => toggle(record.id)}
                                                    aria-expanded={open}
                                                    aria-label={`${open ? 'Hide' : 'Show'} what you changed on ${fullDate(record.date)}`}
                                                    title="Show what you changed"
                                                    className="-ml-1 flex h-6 w-6 items-center justify-center text-arka-teal hover:bg-console-raised"
                                                >
                                                    <ChevronRightIcon className={`h-4 w-4 transition-transform ${open ? 'rotate-90' : ''}`} />
                                                </button>
                                            ) : (
                                                <span className="w-5" aria-hidden="true" />
                                            )}
                                            {fullDate(record.date)}
                                        </div>
                                    </Cell>
                                    <Cell className="text-console-heading">{record.client}</Cell>
                                    <Cell className="font-mono">{record.timeInLabel ?? '—'}</Cell>
                                    <Cell className="font-mono">{record.timeOutLabel ?? '—'}</Cell>
                                    <Cell className="font-mono">{record.hours ?? '—'}</Cell>
                                    <Cell>
                                        <div className="flex flex-wrap gap-1.5">
                                            <StatusBadge status={record.status} label={record.statusLabel} />
                                            {recordFixes.length > 0 && (
                                                <button type="button" onClick={() => toggle(record.id)} className="text-left">
                                                    <Tag tone="live">
                                                        Fixed{recordFixes.length > 1 ? ` ×${recordFixes.length}` : ''}
                                                    </Tag>
                                                </button>
                                            )}
                                        </div>
                                    </Cell>
                                    <td className="py-3 text-right align-top">
                                        {canFix && (
                                            <button type="button" onClick={() => setFixing(record)} className="px-2 py-1 text-xs font-medium text-arka-teal hover:bg-console-raised">
                                                Fix
                                            </button>
                                        )}
                                    </td>
                                </Row>
                                {open && (
                                    <tr className="border-b border-console-line bg-console-raised/40">
                                        <td colSpan={7} className="px-4 py-4">
                                            <p className="mb-2 text-[11px] font-medium uppercase tracking-[0.15em] text-console-muted">
                                                What you changed on {fullDate(record.date)} · {record.client}
                                            </p>
                                            <FixList fixes={recordFixes} showDate={false} />
                                        </td>
                                    </tr>
                                )}
                            </Fragment>
                        );
                    })}
                </Table>
            </div>

            <Dialog open={fixing !== null} onClose={() => setFixing(null)} side title="Fix this day" description={`You can fix days until ${dateTime(fixDeadline)}.`}>
                {fixing && <VerificationFixForm key={fixing.id} periodId={periodId} record={fixing} onDone={() => setFixing(null)} />}
            </Dialog>

            <ConfirmDialog
                open={confirming}
                title="Submit attendance as verified?"
                body="You confirm that your attendance for this period is correct. You can no longer fix it after submitting."
                confirmLabel={processing ? 'Submitting…' : 'Submit as verified'}
                processing={processing}
                onConfirm={submit}
                onClose={() => !processing && setConfirming(false)}
            />
        </Panel>
    );
}

function CalendarView({ month, records }) {
    const byDate = useMemo(() => {
        const map = {};
        records.forEach((record) => (map[record.date] ??= []).push(record));
        return map;
    }, [records]);

    const first = parseDate(`${month}-01`);
    const days = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
    const offset = (first.getDay() + 6) % 7; // weeks start on Monday
    const cells = [...Array(offset).fill(null), ...Array.from({ length: days }, (_, i) => `${month}-${String(i + 1).padStart(2, '0')}`)];

    return (
        <div className="overflow-x-auto">
            <div className="grid min-w-[640px] grid-cols-7 border-l border-t border-console-line">
                {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((day) => (
                    <div key={day} className="border-b border-r border-console-line px-2 py-1.5 text-[11px] uppercase tracking-[0.15em] text-console-muted">
                        {day}
                    </div>
                ))}
                {cells.map((date, index) => (
                    <div key={date ?? `blank-${index}`} className={`min-h-[84px] border-b border-r border-console-line p-2 ${date === todayIso() ? 'bg-arka-teal/5' : ''}`}>
                        {date && (
                            <>
                                <p className="font-mono text-xs text-console-muted">{Number(date.slice(-2))}</p>
                                <div className="mt-1 flex flex-col gap-1">
                                    {(byDate[date] ?? []).map((record) => (
                                        <span key={record.id} title={`${record.client} · ${record.statusLabel}`}>
                                            <StatusBadge status={record.status} label={record.statusLabel} />
                                        </span>
                                    ))}
                                </div>
                            </>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}

function AttendanceTabs({ tab, onChange, verification }) {
    const tabs = [
        ['history', 'Attendance history'],
        ['verification', 'Payroll verification'],
    ];

    return (
        <nav className="flex gap-6 overflow-x-auto border-b border-console-line [scrollbar-width:none]" role="tablist" aria-label="Attendance sections">
            {tabs.map(([value, label]) => (
                <button
                    key={value}
                    type="button"
                    role="tab"
                    aria-selected={tab === value}
                    onClick={() => onChange(value)}
                    className={`-mb-px inline-flex items-center gap-2 whitespace-nowrap border-b-2 pb-3 font-condensed text-[17px] font-semibold transition-colors ${
                        tab === value ? 'border-arka-teal text-console-text' : 'border-transparent text-console-muted hover:text-arka-teal'
                    }`}
                >
                    {label}
                    {/* Waiting on the contractor: verification is open and not submitted yet. */}
                    {value === 'verification' && verification && !verification.verifiedAt && <span className="h-2 w-2 rounded-full bg-arka-teal" aria-label="Action needed" />}
                </button>
            ))}
        </nav>
    );
}

export default function Attendance({ month, summary, records, tab, verification }) {
    const [view, setView] = useState('table');

    // The tab lives in the URL so it survives saving a fix and can be linked from notifications.
    const visit = (params) =>
        router.get(
            route('employee.attendance.index'),
            {
                month,
                tab,
                ...params,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    const goTo = (next) => visit({ month: next });

    const tiles = [
        ['Present days', summary.present, true],
        ['Absent days', summary.absent],
        ['Late days', summary.late],
        ['Overtime days', summary.overtime],
        ['Paid leave', summary.paidLeave],
        ['Unpaid leave', summary.unpaidLeave],
    ];

    return (
        <AppLayout title="My summary" eyebrow="Attendance">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <AttendanceTabs tab={tab} onChange={(next) => visit({ tab: next })} verification={verification} />

                {tab === 'history' && (
                    <>
                        <dl className="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
                            {tiles.map(([label, value, highlight]) => (
                                <div key={label} className={`border px-5 py-4 ${highlight ? 'border-arka-teal/40 bg-arka-teal/5' : 'border-console-line'}`}>
                                    <dt>
                                        <Eyebrow>{label}</Eyebrow>
                                    </dt>
                                    <dd className="mt-2 font-mono text-3xl font-medium text-console-heading">{value}</dd>
                                </div>
                            ))}
                        </dl>

                        <Panel>
                            <PanelHeading
                                title="Attendance history"
                                subtitle={monthLabel(month)}
                            />

                            <div className="mb-5 mt-6 flex flex-wrap items-center justify-between gap-3">
                                <div className="flex items-center gap-1">
                                    <button
                                        type="button"
                                        onClick={() => goTo(shiftMonth(month, -1))}
                                        className="border border-console-line p-2 text-console-muted hover:border-arka-teal hover:text-arka-teal"
                                        aria-label="Previous month"
                                    >
                                        <ChevronLeftIcon className="h-4 w-4" />
                                    </button>
                                    <input
                                        type="month"
                                        aria-label="Month"
                                        value={month}
                                        max={todayIso().slice(0, 7)}
                                        onChange={(e) => e.target.value && goTo(e.target.value)}
                                        className={control}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => goTo(shiftMonth(month, 1))}
                                        disabled={month >= todayIso().slice(0, 7)}
                                        className="border border-console-line p-2 text-console-muted hover:border-arka-teal hover:text-arka-teal disabled:opacity-40"
                                        aria-label="Next month"
                                    >
                                        <ChevronRightIcon className="h-4 w-4" />
                                    </button>
                                </div>
                                <div className="flex border border-console-line" role="tablist" aria-label="View">
                                    {[
                                        ['table', 'Table', ListIcon],
                                        ['calendar', 'Calendar', CalendarDaysIcon],
                                    ].map(([value, label, Icon]) => (
                                        <button
                                            key={value}
                                            type="button"
                                            role="tab"
                                            aria-selected={view === value}
                                            onClick={() => setView(value)}
                                            className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-sm transition-colors ${view === value ? 'bg-arka-teal text-white' : 'text-console-muted hover:text-arka-teal'}`}
                                        >
                                            <Icon className="h-4 w-4" /> {label}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {view === 'table' ? (
                                <Table
                                    columns={['Date', 'Client', 'Time in', 'Time out', 'Hours', 'Status']}
                                    actions={false}
                                    isEmpty={records.length === 0}
                                    emptyMessage="No attendance recorded this month."
                                >
                                    {records.map((record) => (
                                        <Row key={record.id}>
                                            <Cell className="font-mono">{fullDate(record.date)}</Cell>
                                            <Cell className="text-console-heading">{record.client}</Cell>
                                            <Cell className="font-mono">{record.timeInLabel ?? '—'}</Cell>
                                            <Cell className="font-mono">{record.timeOutLabel ?? '—'}</Cell>
                                            <Cell className="font-mono">{record.hours ?? '—'}</Cell>
                                            <Cell>
                                                <StatusBadge status={record.status} label={record.statusLabel} />
                                            </Cell>
                                        </Row>
                                    ))}
                                </Table>
                            ) : (
                                <CalendarView month={month} records={records} />
                            )}
                        </Panel>
                    </>
                )}

                {tab === 'verification' &&
                    (verification ? (
                        <VerificationPanel verification={verification} />
                    ) : (
                        <Panel>
                            <PanelHeading title="Payroll verification" subtitle="Nothing to verify right now." />
                            <p className="mt-4 text-sm text-console-muted">
                                When a payroll period opens for verification, you'll get a notification and can check your attendance here, fix any day on the day it opens, and submit it as
                                verified.
                            </p>
                        </Panel>
                    ))}
            </div>

        </AppLayout>
    );
}
