import Panel, { Eyebrow, MetricRow, PanelHeading } from '@/Components/Console/Panel';
import RecentNotifications from '@/Components/Console/RecentNotifications';
import { ArrowRightIcon } from '@/Components/Icons';
import AppLayout from '@/Layouts/AppLayout';
import { fullDate, greeting, shortDate } from '@/lib/format';
import { Link, usePage, usePoll } from '@inertiajs/react';

function CardLink({ href, children }) {
    return (
        <Link href={href} className="group inline-flex items-center gap-1.5 text-sm font-medium text-arka-teal">
            {children}
            <ArrowRightIcon className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
        </Link>
    );
}

function StatTiles({ tiles, compact = false }) {
    return (
        <dl className={`mt-6 grid grid-cols-2 gap-3 ${compact ? "" : "sm:grid-cols-4"}`}>
            {tiles.map(([label, value, highlight]) => (
                <div key={label} className={`border px-4 py-3 ${highlight ? 'border-arka-teal/40 bg-arka-teal/5' : 'border-console-line'}`}>
                    <dt>
                        <Eyebrow>{label}</Eyebrow>
                    </dt>
                    <dd className="mt-1 font-mono text-3xl font-medium text-console-heading">{value}</dd>
                </div>
            ))}
        </dl>
    );
}

function EmployeeOverview({ employees }) {
    return (
        <Panel className="lg:col-span-2">
            <PanelHeading
                title="Contractor overview"
                subtitle="Accounts you manage"
                action={<CardLink href={route('admin.employees.index')}>View all</CardLink>}
            />
            <StatTiles
                tiles={[
                    ['Active', employees.active, true],
                    ['Inactive', employees.inactive],
                    ['New this week', employees.newThisWeek],
                    ['Awaiting first login', employees.awaitingFirstLogin],
                ]}
            />
        </Panel>
    );
}

function TodayAttendance({ attendance }) {
    return (
        <Panel>
            <PanelHeading
                title="Today's attendance"
                subtitle={shortDate(attendance.date)}
                action={<CardLink href={route('admin.attendance.index')}>View details</CardLink>}
            />
            <StatTiles
                compact
                tiles={[
                    ['Present', attendance.present, true],
                    ['Late', attendance.late],
                    ['Absent', attendance.absent],
                    ['Incomplete', attendance.incomplete],
                ]}
            />
            {attendance.onLeave > 0 && <p className="mt-4 font-mono text-xs text-console-muted">{attendance.onLeave} on leave today</p>}
        </Panel>
    );
}

function IncompleteAlerts({ incomplete }) {
    return (
        <Panel>
            <PanelHeading
                title="Incomplete attendance"
                subtitle="Missing clock-outs to review"
                action={incomplete.total > incomplete.items.length && <CardLink href={route('admin.attendance.index', { status: 'incomplete' })}>All {incomplete.total}</CardLink>}
            />
            {incomplete.items.length === 0 ? (
                <p className="mt-6 text-sm italic text-console-muted">Every recent attendance record is complete.</p>
            ) : (
                <ul className="mt-6 border-t border-console-line">
                    {incomplete.items.map((item) => (
                        <li key={item.id} className="flex items-center justify-between gap-3 border-b border-console-line py-2.5">
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium text-console-heading">{item.employee}</p>
                                <p className="truncate font-mono text-xs text-console-muted">
                                    {fullDate(item.date)} · in {item.timeIn ?? '—'}
                                    {item.client ? ` · ${item.client}` : ''}
                                </p>
                            </div>
                            <Link href={item.fixUrl} className="shrink-0 text-sm font-medium text-arka-teal hover:underline">
                                Fix this
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}

function ActiveSchedules({ onShift }) {
    return (
        <Panel>
            <PanelHeading title="Active schedules" subtitle="Contractors on shift right now" action={<CardLink href={route('admin.scheduling.index')}>Schedules</CardLink>} />
            <p className="mt-6 font-mono text-4xl font-medium text-console-heading">
                {onShift.now} <span className="text-2xl text-console-muted">/ {onShift.scheduledToday}</span>
            </p>
            <p className="mt-1 text-xs text-console-muted">on shift now / scheduled today</p>
            {onShift.byClient.length > 0 && (
                <div className="mt-5 border-t border-console-line">
                    {onShift.byClient.map((row) => (
                        <MetricRow key={row.client} label={row.client} value={row.count} />
                    ))}
                </div>
            )}
        </Panel>
    );
}

function PendingDevotionals({ devotionals }) {
    const percent = devotionals.expected ? Math.round((devotionals.submitted / devotionals.expected) * 100) : 0;

    return (
        <Panel>
            <PanelHeading
                title="Pending devotionals"
                subtitle="Not yet submitted today"
                action={<CardLink href={route('admin.devotionals.index', { status: 'not_submitted' })}>View list</CardLink>}
            />
            <p className="mt-6 font-mono text-4xl font-medium text-console-heading">{devotionals.pending}</p>
            <div className="mt-4 h-1.5 w-full bg-console-track" role="img" aria-label={`${devotionals.submitted} of ${devotionals.expected} submitted`}>
                <div className="h-full bg-arka-aqua transition-all duration-500" style={{ width: `${percent}%` }} />
            </div>
            <p className="mt-2 font-mono text-xs text-console-muted">
                {devotionals.submitted} of {devotionals.expected} submitted
            </p>
            {devotionals.names.length > 0 && <p className="mt-4 text-sm text-console-muted">{devotionals.names.join(', ')}{devotionals.pending > devotionals.names.length ? '…' : ''}</p>}
        </Panel>
    );
}

export default function Dashboard({ employees, attendance, incomplete, onShift, devotionals, summary }) {
    const { auth } = usePage().props;

    usePoll(60_000, { only: ['employees', 'attendance', 'incomplete', 'onShift', 'devotionals', 'summary', 'notifications'] });

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
                    <EmployeeOverview employees={employees} />
                    <TodayAttendance attendance={attendance} />
                    <IncompleteAlerts incomplete={incomplete} />
                    <ActiveSchedules onShift={onShift} />
                    <PendingDevotionals devotionals={devotionals} />
                    <RecentNotifications className="lg:col-span-3" />
                </div>
            </div>
        </AppLayout>
    );
}
