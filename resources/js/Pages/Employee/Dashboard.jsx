import { ConsoleButton, SecondaryButton } from '@/Components/Console/Field';
import Panel, { Eyebrow, PanelHeading } from '@/Components/Console/Panel';
import RecentNotifications from '@/Components/Console/RecentNotifications';
import StatusBadge from '@/Components/Console/StatusBadge';
import { ArrowRightIcon, CheckIcon, PlayIcon, StopIcon } from '@/Components/Icons';
import AppLayout from '@/Layouts/AppLayout';
import { dateRange, greeting, peso, timeAgo } from '@/lib/format';
import { Link, router, usePage, usePoll } from '@inertiajs/react';
import { useState } from 'react';
import CombinedTotal from '@/Components/Employee/CombinedTotal';

function CardLink({ href, children }) {
    return (
        <Link href={href} className="group inline-flex items-center gap-1.5 text-sm font-medium text-arka-teal">
            {children}
            <ArrowRightIcon className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
        </Link>
    );
}

const workStatus = {
    running: ['running', 'Clocked in'],
    on_break: ['on_break', 'On break'],
    stopped: ['stopped', 'Clocked out'],
    not_started: ['not_started', 'Not started'],
};

function ActiveTimeTracker({ timers }) {
    const [busy, setBusy] = useState(false);
    // Only clients whose shift is open for the timer right now (10 minutes before it starts until it ends).
    const idle = timers.clients.filter((client) => !client.timer && client.canStart && !client.locked);

    const run = (url, data = {}) => router.post(url, data, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });

    return (
        <Panel className="lg:col-span-2">
            <PanelHeading
                title="Active time tracker"
                subtitle="Combined across every client you serve today"
                action={<CardLink href={route('employee.time-tracker.index')}>Open Time Tracker</CardLink>}
            />
            <div className="mt-6 flex flex-wrap items-end justify-between gap-6">
                <div>
                    <p className="font-mono text-5xl font-medium tabular-nums text-console-heading">
                        <CombinedTotal board={timers} />
                    </p>
                    <span className={`mt-3 inline-flex items-center gap-1.5 border px-2 py-0.5 text-xs ${timers.running > 0 ? 'border-arka-teal/30 bg-arka-teal/10 text-arka-teal' : 'border-console-line text-console-muted'}`}>
                        {timers.running > 0 && <span className="h-1.5 w-1.5 rounded-full bg-arka-teal" />}
                        {timers.running} {timers.running === 1 ? 'timer' : 'timers'} active
                    </span>
                </div>
                <div className="flex flex-wrap gap-2">
                    {idle.length === 1 && (
                        <ConsoleButton disabled={busy} onClick={() => run(route('employee.time-tracker.start'), { client_id: idle[0].id })}>
                            <PlayIcon className="h-4 w-4" /> Start a timer · {idle[0].name}
                        </ConsoleButton>
                    )}
                    {idle.length > 1 && (
                        <ConsoleButton onClick={() => router.visit(route('employee.time-tracker.index'))}>
                            <PlayIcon className="h-4 w-4" /> Start a timer
                        </ConsoleButton>
                    )}
                    <SecondaryButton disabled={busy || timers.running === 0} onClick={() => run(route('employee.time-tracker.stop-all'))}>
                        <StopIcon className="h-4 w-4" /> Stop all
                    </SecondaryButton>
                </div>
            </div>
            {timers.clients.length === 0 && (
                <p className="mt-5 text-sm italic text-console-muted">Timers appear once your administrator assigns you to a client.</p>
            )}
        </Panel>
    );
}

function DevotionalCard({ devotional }) {
    const percent = devotional.daysSoFar ? Math.min(100, Math.round((devotional.thisMonth / devotional.daysSoFar) * 100)) : 0;

    return (
        <Panel>
            <PanelHeading title="Devotional" subtitle="For your own record" action={<CardLink href={route('employee.devotionals.index')}>Open</CardLink>} />
            <div className="mt-6 flex items-center gap-3">
                <span
                    className={`flex h-7 w-7 items-center justify-center border ${
                        devotional.submittedToday ? 'border-arka-teal bg-arka-teal text-white' : 'border-console-mark text-transparent'
                    }`}
                >
                    <CheckIcon className="h-4 w-4" />
                </span>
                <p className={devotional.submittedToday ? 'font-medium text-console-heading' : 'text-console-muted'}>
                    {devotional.submittedToday ? 'Submitted today' : 'Not submitted yet — open until midnight'}
                </p>
            </div>
            <div className="mt-6 h-1.5 w-full bg-console-track" role="img" aria-label={`${devotional.thisMonth} of ${devotional.daysSoFar} days`}>
                <div className="h-full bg-arka-aqua transition-all duration-500" style={{ width: `${percent}%` }} />
            </div>
            <p className="mt-2 font-mono text-xs text-console-muted">
                {devotional.thisMonth} of {devotional.daysSoFar} days this month
            </p>
        </Panel>
    );
}

function TodayWorkStatus({ clients }) {
    return (
        <Panel>
            <PanelHeading title="Today's work status" subtitle="Per client" action={<CardLink href={route('employee.time-tracker.index')}>Details</CardLink>} />
            {clients.length === 0 ? (
                <p className="mt-6 text-sm italic text-console-muted">No client assignments yet.</p>
            ) : (
                <ul className="mt-6 border-t border-console-line">
                    {clients.map((client) => {
                        const [status, label] = workStatus[client.status] ?? workStatus.not_started;

                        return (
                            <li key={client.id} className="flex items-center justify-between gap-3 border-b border-console-line py-2.5">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium text-console-heading">{client.name}</p>
                                    <p className="truncate font-mono text-xs text-console-muted">{client.scheduled?.label ?? 'No schedule today'}</p>
                                </div>
                                <StatusBadge status={status} label={label} />
                            </li>
                        );
                    })}
                </ul>
            )}
        </Panel>
    );
}

function AttendanceThisMonth({ attendance }) {
    const tiles = [
        ['Present', attendance.present, true],
        ['Absent', attendance.absent],
        ['Late', attendance.late],
        ['Overtime', attendance.overtime],
    ];

    return (
        <Panel>
            <PanelHeading title="Attendance this month" action={<CardLink href={route('employee.attendance.index')}>Details</CardLink>} />
            <dl className="mt-6 grid grid-cols-2 gap-3">
                {tiles.map(([label, value, highlight]) => (
                    <div key={label} className={`border px-4 py-3 ${highlight ? 'border-arka-teal/40 bg-arka-teal/5' : 'border-console-line'}`}>
                        <dt>
                            <Eyebrow>{label}</Eyebrow>
                        </dt>
                        <dd className="mt-1 font-mono text-3xl font-medium text-console-heading">{value}</dd>
                    </div>
                ))}
            </dl>
        </Panel>
    );
}

function PendingLeave({ leave }) {
    const withdraw = () => router.post(route('employee.leave.cancel', leave.id), {}, { preserveScroll: true });

    return (
        <Panel>
            <PanelHeading title="Pending leave request" action={<CardLink href={route('employee.leave.index')}>Leave</CardLink>} />
            {!leave ? (
                <p className="mt-6 text-sm italic text-console-muted">No leave request waiting. Need time off? File it in Leave Request.</p>
            ) : (
                <div className="mt-6">
                    <p className="font-mono text-sm text-console-heading">{dateRange(leave.startDate, leave.endDate)}</p>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <StatusBadge status={leave.status} label={leave.statusLabel} />
                        <span className="text-xs text-console-muted">{leave.paid ? 'Paid' : 'Unpaid'}</span>
                    </div>
                    <p className="mt-3 text-sm text-console-text">{leave.reason}</p>
                    <p className="mt-3 text-xs text-console-muted">
                        Submitted {timeAgo(leave.submittedAt)}
                        {leave.canWithdraw && (
                            <>
                                {' '}— you can still{' '}
                                <button type="button" onClick={withdraw} className="text-arka-teal hover:underline">
                                    withdraw it
                                </button>
                                .
                            </>
                        )}
                    </p>
                    <div className="mt-4">
                        <CardLink href={route('employee.leave.index')}>View request</CardLink>
                    </div>
                </div>
            )}
        </Panel>
    );
}

function LatestPayslip({ payslip }) {
    return (
        <Panel>
            <PanelHeading title="Latest payslip" subtitle={payslip ? payslip.period : 'Nothing released yet'} />
            {payslip ? (
                <>
                    <Eyebrow className="mt-6">Net pay</Eyebrow>
                    <p className="mt-1 font-mono text-4xl font-medium text-console-heading">{peso(payslip.net)}</p>
                    <p className="mt-1 font-mono text-xs text-console-muted">{dateRange(payslip.periodStart, payslip.periodEnd)}</p>
                    <ConsoleButton className="mt-6" onClick={() => router.visit(route('employee.payslips.index', { open: payslip.periodId }))}>
                        View payslip
                    </ConsoleButton>
                </>
            ) : (
                <p className="mt-6 text-sm italic text-console-muted">Your payslip appears here as soon as payroll is released.</p>
            )}
        </Panel>
    );
}

export default function Dashboard({ timers, devotional, attendance, leave, payslip, summary }) {
    const { auth } = usePage().props;

    usePoll(60_000, { only: ['timers', 'devotional', 'attendance', 'leave', 'payslip', 'summary'] });

    return (
        <AppLayout title="Home">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-9">
                <div>
                    <h2 className="font-condensed text-3xl font-bold text-console-heading">
                        {greeting()}, {auth.user.name.split(' ')[0]}.
                    </h2>
                    <p className="mt-1 text-console-muted">{summary}</p>
                </div>

                <div className="grid gap-9 lg:grid-cols-3 lg:gap-7">
                    <ActiveTimeTracker timers={timers} />
                    <DevotionalCard devotional={devotional} />
                    <TodayWorkStatus clients={timers.clients} />
                    <AttendanceThisMonth attendance={attendance} />
                    <PendingLeave leave={leave} />
                    <LatestPayslip payslip={payslip} />
                    <RecentNotifications className="lg:col-span-2" columns={1} />
                </div>
            </div>
        </AppLayout>
    );
}
