import Panel, { Eyebrow, MetricRow, PanelHeading, ReversedBar } from '@/Components/Console/Panel';
import { ArrowRightIcon } from '@/Components/Icons';
import PayrollOverviewCard from '@/Components/Payroll/PayrollOverviewCard';
import AppLayout from '@/Layouts/AppLayout';
import { dateRange, daysFromNow, peso, shortDate, timeAgo } from '@/lib/format';
import { Link, usePage, usePoll } from '@inertiajs/react';

const REFRESH_MS = 60_000;

// Brand palette only — no red. Teal = live/resolved, cyan = progress, navy = waiting, gray = closed.
const attendanceColors = {
    present: 'bg-arka-teal',
    late: 'bg-arka-aqua',
    undertime: 'bg-arka-navy/60',
    absent: 'bg-console-mark',
    paid_leave: 'bg-console-heading',
    unpaid_leave: 'bg-console-line',
    incomplete: 'bg-arka-gold',
};

const alertStyles = {
    info: 'border-l-arka-teal',
    warning: 'border-l-console-heading',
    danger: 'border-l-arka-gold',
};

function PayrollHero({ payroll, pendingTotal, userName }) {
    const { period, totals, daysUntilRelease } = payroll;

    return (
        <ReversedBar className="grid gap-8 md:grid-cols-[1.4fr_1fr_auto] md:items-center md:gap-6">
            <div>
                <Eyebrow className="!text-white/70">Payroll · {dateRange(period.startDate, period.endDate)}</Eyebrow>
                <p className="mt-2 font-mono text-4xl font-medium tracking-tight">{peso(totals.net)}</p>
                <span className="mt-4 inline-block border border-white/60 px-2 py-0.5 text-sm">{period.statusLabel}</span>
            </div>

            <div>
                <Eyebrow className="!text-white/70">Next salary release</Eyebrow>
                <p className="mt-2 font-mono text-4xl font-medium">{daysFromNow(daysUntilRelease)}</p>
                <p className="mt-2 text-sm text-white/70">{shortDate(period.releaseDate)}</p>
            </div>

            <div>
                <Eyebrow className="!text-white/70">Pending approvals</Eyebrow>
                <p className="mt-2 font-mono text-4xl font-medium text-arka-gold">{pendingTotal}</p>
                <p className="mt-2 text-sm text-white/70">Welcome back, {userName}</p>
            </div>
        </ReversedBar>
    );
}

function BigFigure({ children }) {
    return <p className="mb-5 mt-7 font-mono text-4xl font-medium text-console-heading">{children}</p>;
}

function PendingApprovalsCard({ pendingApprovals }) {
    return (
        <Panel>
            <PanelHeading title="Pending approvals" subtitle="Decisions waiting for the Super Admin" />
            <BigFigure>{pendingApprovals.total}</BigFigure>
            <div className="border-t border-console-line">
                {pendingApprovals.items.map((item) => (
                    <MetricRow key={item.key} label={item.label} value={item.count} href={item.href} />
                ))}
            </div>
            <p className="mt-5 text-sm italic text-console-muted">
                {pendingApprovals.total === 0
                    ? 'Nothing is waiting for your decision.'
                    : `${pendingApprovals.total} item${pendingApprovals.total === 1 ? '' : 's'} ready for your review.`}
            </p>
        </Panel>
    );
}

function AttendanceCard({ attendance }) {
    const { expected, accounted, breakdown } = attendance;
    const scale = Math.max(expected, accounted, 1);

    return (
        <Panel>
            <PanelHeading title="Attendance summary" subtitle={`Today · ${shortDate(attendance.date)}`} />

            <p className="mt-7 font-mono text-4xl font-medium text-console-heading">
                {accounted} <span className="text-2xl text-console-muted">/ {expected}</span>
            </p>

            <div
                className="mt-5 flex h-1.5 w-full overflow-hidden bg-console-track"
                role="img"
                aria-label={`${accounted} of ${expected} employees have an attendance record today`}
            >
                {breakdown.map((status) =>
                    status.count > 0 ? (
                        <div
                            key={status.key}
                            title={`${status.label}: ${status.count}`}
                            className={`${attendanceColors[status.key]} transition-all duration-500`}
                            style={{ width: `${(status.count / scale) * 100}%` }}
                        />
                    ) : null,
                )}
            </div>

            <dl className="mt-5 grid grid-cols-2 gap-x-6 gap-y-1.5 text-sm">
                {breakdown.map((status) => (
                    <div key={status.key} className="flex items-center justify-between">
                        <dt className="flex items-center gap-2 text-console-muted">
                            <span className={`h-2 w-2 ${attendanceColors[status.key]}`} />
                            {status.label}
                        </dt>
                        <dd className="font-mono text-console-text">{status.count}</dd>
                    </div>
                ))}
            </dl>

            <p className="mt-6 font-mono text-xs text-console-muted">
                {attendance.clockedIn} clocked in · {attendance.missingClockOut} missing clock-out · {attendance.correctionsPending} corrections
                pending
            </p>
        </Panel>
    );
}

function WorkforceCard({ workforce }) {
    const pair = ({ active, inactive }) => `${active} · ${inactive} inactive`;

    return (
        <Panel>
            <PanelHeading title="Workforce overview" subtitle="Active accounts and client coverage" />
            <BigFigure>{workforce.activeTotal}</BigFigure>
            <div className="border-t border-console-line">
                <MetricRow label="Contractors" value={pair(workforce.employees)} href={route('super-admin.workforce.employees.index')} />
                <MetricRow label="Admins" value={pair(workforce.admins)} href={route('super-admin.workforce.admins.index')} />
                <MetricRow label="Active clients" value={workforce.activeClients} href={route('super-admin.workforce.clients.index')} />
            </div>
            <p className="mt-5 text-sm italic text-console-muted">Admins and contractors with an active account.</p>
        </Panel>
    );
}

function AlertsCard({ alerts }) {
    return (
        <Panel>
            <PanelHeading title="Alerts" subtitle="What needs attention next" />
            {alerts.length === 0 ? (
                <p className="mt-6 text-sm italic text-console-muted">No alerts right now.</p>
            ) : (
                <ul className="mt-6 flex flex-col gap-3">
                    {alerts.map((alert) => {
                        const body = (
                            <>
                                <p className="text-sm font-medium text-console-heading">{alert.title}</p>
                                <p className="mt-1 text-xs leading-relaxed text-console-muted">{alert.message}</p>
                            </>
                        );
                        const className = `block border border-console-line border-l-2 ${alertStyles[alert.level]} bg-console-panel px-4 py-3`;

                        return (
                            <li key={alert.title}>
                                {alert.href ? (
                                    <Link href={alert.href} className={`${className} transition-colors hover:bg-console-raised`}>
                                        {body}
                                    </Link>
                                ) : (
                                    <div className={className}>{body}</div>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </Panel>
    );
}

function RecentActivityCard({ activity }) {
    return (
        <Panel>
            <PanelHeading
                title="Recent activity"
                subtitle="Latest important system actions"
                action={
                    <Link href={route('super-admin.activity-logs')} className="text-sm text-arka-teal hover:underline">
                        View logs
                    </Link>
                }
            />
            {activity.length === 0 ? (
                <p className="mt-6 text-sm italic text-console-muted">No activity recorded yet.</p>
            ) : (
                <ul className="mt-6 border-t border-console-line">
                    {activity.map((entry) => (
                        <li key={entry.id} className="flex items-start justify-between gap-4 border-b border-console-line py-2.5 text-sm">
                            <div className="min-w-0">
                                <p className="truncate text-console-text">{entry.action}</p>
                                <p className="mt-0.5 truncate text-xs text-console-dim">
                                    {entry.details ?? entry.module}
                                    {entry.user ? ` · ${entry.user}` : ''}
                                </p>
                            </div>
                            <span className="shrink-0 font-mono text-xs text-console-dim">{timeAgo(entry.at)}</span>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}

export default function Dashboard({ payroll, pendingApprovals, attendance, workforce, alerts, recentActivity }) {
    const { auth } = usePage().props;

    // Keep the numbers live without a manual refresh.
    usePoll(REFRESH_MS, {
        only: ['payroll', 'pendingApprovals', 'attendance', 'workforce', 'alerts', 'recentActivity', 'notifications'],
    });

    return (
        <AppLayout title="Dashboard">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-9">
                <PayrollHero payroll={payroll} pendingTotal={pendingApprovals.total} userName={auth.user.name} />

                <div className="grid gap-9 lg:grid-cols-2 xl:grid-cols-3 xl:gap-7">
                    <PendingApprovalsCard pendingApprovals={pendingApprovals} />
                    <AttendanceCard attendance={attendance} />
                    <WorkforceCard workforce={workforce} />
                </div>

                <PayrollOverviewCard overview={payroll} />

                <div className="grid gap-9 lg:grid-cols-2 xl:gap-7">
                    <AlertsCard alerts={alerts} />
                    <RecentActivityCard activity={recentActivity} />
                </div>
            </div>
        </AppLayout>
    );
}
