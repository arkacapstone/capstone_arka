import { ConsoleButton, SecondaryButton } from '@/Components/Console/Field';
import Panel, { Eyebrow, PanelHeading, ReversedBar } from '@/Components/Console/Panel';
import StatusBadge from '@/Components/Console/StatusBadge';
import { ArrowRightIcon, CoffeeIcon, PlayIcon, StopIcon } from '@/Components/Icons';
import CombinedTotal from '@/Components/Employee/CombinedTotal';
import DaySessions from '@/Components/Employee/DaySessions';
import AppLayout from '@/Layouts/AppLayout';
import { hms, hoursMinutes } from '@/lib/format';
import useTicker from '@/lib/useTicker';
import { Link, router, usePage, usePoll } from '@inertiajs/react';
import { useState } from 'react';

const cardStatus = {
    running: 'Running',
    on_break: 'On break',
    stopped: 'Stopped',
    not_started: 'Not started',
    shift_ended: 'Shift ended',
};

function post(url, data = {}, setBusy) {
    router.post(url, data, {
        preserveScroll: true,
        onStart: () => setBusy(true),
        onFinish: () => setBusy(false),
    });
}

function ClientCard({ client, serverNow }) {
    const [busy, setBusy] = useState(false);
    const timer = client.timer;
    const elapsed = useTicker(timer?.status === 'running', serverNow);
    const seconds = (timer ? timer.workedSeconds + elapsed : 0) + client.completedSeconds;

    return (
        <Panel className="flex flex-col">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="truncate font-condensed text-[22px] font-bold leading-tight text-console-heading">{client.name}</h2>
                    <p className="mt-1 text-sm text-console-muted">
                        {[client.position, client.employmentType].filter(Boolean).join(' · ') || 'Position set on your schedule'}
                    </p>
                </div>
                <StatusBadge status={client.status} label={cardStatus[client.status]} />
            </div>

            <p className={`mt-7 font-mono text-5xl font-medium tabular-nums ${timer ? 'text-console-heading' : 'text-console-dim'}`} aria-live="off">
                {hms(seconds)}
            </p>
            <p className="mt-2 font-mono text-xs text-console-muted">
                {client.scheduled ? `Scheduled ${client.scheduled.hours}h · ${client.scheduled.label}` : 'No schedule today'}
            </p>

            {timer?.status === 'on_break' && (
                <p className="mt-3 text-xs text-console-muted">On break since {new Date(timer.breakStartedAt).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}.</p>
            )}
            {timer?.longRunning && timer.status === 'running' && (
                <p className="mt-3 text-xs italic text-console-muted">Running 3h+ — just checking you're still on this one.</p>
            )}

            {!timer && client.startHint && <p className="mt-3 text-xs text-console-muted">{client.startHint}</p>}

            <div className="mt-auto flex flex-wrap gap-2 pt-6">
                {!timer && (
                    <ConsoleButton
                        disabled={busy || client.locked || !client.canStart}
                        title={client.locked ? 'This day is already in a locked or released payroll.' : (client.startHint ?? undefined)}
                        onClick={() => post(route('employee.time-tracker.start'), { client_id: client.id }, setBusy)}
                    >
                        <PlayIcon className="h-4 w-4" /> {client.completedSeconds > 0 ? 'Start again' : 'Start timer'}
                    </ConsoleButton>
                )}
                {timer?.status === 'running' && (
                    <ConsoleButton disabled={busy} onClick={() => post(route('employee.time-tracker.break', timer.id), {}, setBusy)}>
                        <CoffeeIcon className="h-4 w-4" /> Break
                    </ConsoleButton>
                )}
                {timer?.status === 'on_break' && (
                    <ConsoleButton disabled={busy} onClick={() => post(route('employee.time-tracker.break', timer.id), {}, setBusy)}>
                        <PlayIcon className="h-4 w-4" /> Resume
                    </ConsoleButton>
                )}
                {timer && (
                    <SecondaryButton disabled={busy} onClick={() => post(route('employee.time-tracker.stop', timer.id), {}, setBusy)}>
                        <StopIcon className="h-4 w-4" /> Stop
                    </SecondaryButton>
                )}
            </div>
        </Panel>
    );
}

export default function TimeTracker({ board }) {
    const { errors } = usePage().props;
    const [stopping, setStopping] = useState(false);
    const error = errors.client_id ?? errors.timer;

    usePoll(60_000, { only: ['board'] });

    return (
        <AppLayout title="Active sessions" eyebrow="Time tracker">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <ReversedBar className="grid gap-6 md:grid-cols-3 md:items-end">
                    <div>
                        <Eyebrow className="!text-white/70">Combined running total</Eyebrow>
                        <p className="mt-2 font-mono text-5xl font-medium tabular-nums">
                            <CombinedTotal board={board} />
                        </p>
                        <span className="mt-3 inline-flex items-center gap-1.5 border border-white/30 px-2 py-0.5 text-xs">
                            {board.running > 0 && <span className="h-1.5 w-1.5 rounded-full bg-arka-aqua" />}
                            {board.running} of {board.totalClients} timers running
                        </span>
                    </div>
                    <div>
                        <Eyebrow className="!text-white/70">Scheduled today</Eyebrow>
                        <p className="mt-2 font-mono text-2xl">{hoursMinutes(board.scheduledMinutes)}</p>
                    </div>
                    <div className="flex md:justify-end">
                        <button
                            type="button"
                            disabled={board.running === 0 || stopping}
                            onClick={() => post(route('employee.time-tracker.stop-all'), {}, setStopping)}
                            className="inline-flex items-center gap-2 border border-white/40 px-4 py-2 text-sm font-medium text-white transition-colors hover:border-white hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <StopIcon className="h-4 w-4" /> Stop all timers
                        </button>
                    </div>
                </ReversedBar>

                {board.locked ? (
                    <p className="border border-console-line border-l-2 border-l-arka-gold px-4 py-3 text-sm text-console-text">
                        Today is part of a payroll period that is already locked or released, so its time has been paid out and new timers can&apos;t start. Timers
                        start again from zero on the first day of the next open period. Contact the Super Admin if this is a mistake.
                    </p>
                ) : (
                    error && <p className="border border-console-error/40 px-4 py-3 text-sm text-console-error">{error}</p>
                )}

                {board.clients.length === 0 ? (
                    <Panel>
                        <PanelHeading title="No clients yet" subtitle="Timers appear here once your administrator assigns you to a client." />
                    </Panel>
                ) : (
                    <div className="grid gap-9 md:grid-cols-2 xl:grid-cols-3 xl:gap-7">
                        {board.clients.map((client) => (
                            <ClientCard key={client.id} client={client} serverNow={board.serverNow} />
                        ))}
                    </div>
                )}

                <Panel>
                    <PanelHeading
                        title="Today's summary"
                        subtitle="Every session you tracked today, across all clients."
                        action={
                            <Link href={route('employee.time-history.index')} className="group inline-flex items-center gap-1.5 text-sm font-medium text-arka-teal">
                                View all history <ArrowRightIcon className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                            </Link>
                        }
                    />
                    <div className="mt-6">
                        {board.today.length === 0 ? (
                            <p className="py-6 text-sm italic text-console-muted">No sessions yet today. Start a timer on a client card above.</p>
                        ) : (
                            <div className="flex flex-col gap-2">
                                {Object.entries(
                                    board.today.reduce((days, session) => {
                                        (days[session.date] ??= []).push(session);
                                        return days;
                                    }, {}),
                                )
                                    .sort(([a], [b]) => b.localeCompare(a))
                                    .map(([date, sessions]) => (
                                        <DaySessions key={date} date={date} sessions={sessions} fixes={board.todayFixes?.[date] ?? []} defaultOpen />
                                    ))}
                            </div>
                        )}
                    </div>
                    <p className="mt-4 text-xs text-console-muted">
                        Break allowance is set for each client and shown here for your own awareness only — going over never reduces your pay.
                    </p>
                </Panel>
            </div>
        </AppLayout>
    );
}
