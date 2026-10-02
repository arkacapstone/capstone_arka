import Dialog from '@/Components/Console/Dialog';
import Field, { ConsoleButton, SelectField } from '@/Components/Console/Field';
import Pagination from '@/Components/Console/Pagination';
import Panel, { Eyebrow, PanelHeading } from '@/Components/Console/Panel';
import StatusBadge from '@/Components/Console/StatusBadge';
import SchedulingTabs from '@/Components/Scheduling/SchedulingTabs';
import ConfirmDialog from '@/Components/Workforce/ConfirmDialog';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import useFilters, { SearchInput } from '@/Components/Workforce/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import { clock, fullDate, todayIso } from '@/lib/format';
import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const filterSelect =
    'rounded-none border border-console-line bg-console-panel py-2 pl-3 pr-9 text-sm text-console-text transition-colors hover:border-arka-aqua focus:border-arka-teal focus:ring-1 focus:ring-arka-teal';

/** "09:00" + 8 → "17:00" (wraps past midnight). */
function addHours(start, hours) {
    const minutes = (Number(start.slice(0, 2)) * 60 + Number(start.slice(3, 5)) + hours * 60) % 1440;

    return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
}

const typeLabels = { full_time: 'Full-Time', part_time: 'Part-Time' };

/** A contractor works at most five days a week, across all their clients. */
const MAX_WORKING_DAYS = 5;

function expectedHours(start, end) {
    if (!start || !end) return null;

    const toMinutes = (value) => Number(value.slice(0, 2)) * 60 + Number(value.slice(3, 5));
    let minutes = toMinutes(end) - toMinutes(start);
    if (minutes <= 0) minutes += 1440;

    return { hours: (minutes / 60).toFixed(2).replace(/\.00$/, ''), overnight: toMinutes(end) <= toMinutes(start) };
}

/** The week at a glance: each day is filled when the contractor works it. */
function WeekStrip({ days, weekdays }) {
    return (
        <div className="flex gap-1" aria-label={`Works ${days.length} days: ${days.join(', ')}`}>
            {weekdays.map((day) => {
                const on = days.includes(day.value);

                return (
                    <span
                        key={day.value}
                        title={day.label}
                        className={`flex h-6 w-7 items-center justify-center border font-mono text-[10px] uppercase ${
                            on ? 'border-arka-teal bg-arka-teal text-white' : 'border-console-line text-console-dim'
                        }`}
                    >
                        {day.label.slice(0, 2)}
                    </span>
                );
            })}
        </div>
    );
}

function ScheduleForm({ schedule, employees, clients, weekdays, shiftHours, preselectEmployee, preselectClient, onDone }) {
    const changing = Boolean(schedule);
    const { data, setData, post, put, processing, errors } = useForm({
        ...(changing ? { effective_date: todayIso() } : { employee_id: preselectEmployee ?? employees[0]?.value ?? '', start_date: todayIso() }),
        client_id: schedule?.clientId ?? preselectClient ?? '',
        working_days: schedule?.workingDays ?? ['mon', 'tue', 'wed', 'thu', 'fri'],
        start_time: schedule?.startTime ?? '09:00',
        end_time: schedule?.endTime ?? '18:00',
        break_allowance_minutes: schedule?.breakAllowance ?? 60,
        end_date: schedule?.endDate ?? '',
    });

    const employeeId = changing ? schedule.employeeId : Number(data.employee_id);
    const employee = employees.find((option) => option.value === employeeId);
    const assignedClients = clients.filter((client) => employee?.clients.includes(client.value));

    // Keep the client valid for the chosen contractor: only clients the Super Admin approved.
    useEffect(() => {
        if (!assignedClients.some((client) => client.value === Number(data.client_id))) {
            setData('client_id', assignedClients[0]?.value ?? '');
        }
    }, [employeeId]); // eslint-disable-line react-hooks/exhaustive-deps

    // A Full-Time or Part-Time client fixes the schedule length (System & Rules); the end time follows the start.
    const clientType = employee?.clientTypes?.[data.client_id] ?? null;
    const fixedHours = clientType ? shiftHours[clientType] : null;

    useEffect(() => {
        if (fixedHours && data.start_time) {
            setData('end_time', addHours(data.start_time, fixedHours));
        }
    }, [fixedHours, data.start_time]); // eslint-disable-line react-hooks/exhaustive-deps

    const toggleDay = (day) => {
        if (data.working_days.includes(day)) {
            setData('working_days', data.working_days.filter((value) => value !== day));
        } else if (data.working_days.length < MAX_WORKING_DAYS) {
            setData('working_days', [...data.working_days, day]);
        }
    };
    const dayLimitReached = data.working_days.length >= MAX_WORKING_DAYS;

    const hours = expectedHours(data.start_time, data.end_time);

    const submit = (e) => {
        e.preventDefault();

        const options = { preserveScroll: true, onSuccess: onDone };

        changing ? put(route('admin.scheduling.update', schedule.id), options) : post(route('admin.scheduling.store'), options);
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-5">
            {changing ? (
                <div className="border border-console-line bg-console-raised px-4 py-3 text-sm">
                    <p className="font-medium text-console-heading">{schedule.employee.name}</p>
                    <p className="mt-1 text-xs leading-relaxed text-console-muted">
                        A change starting after {fullDate(schedule.startDate)} ends the current schedule the day before and keeps it in history.
                    </p>
                </div>
            ) : (
                <SelectField
                    id="employee_id"
                    label="Contractor"
                    value={data.employee_id}
                    onChange={(e) => setData('employee_id', e.target.value)}
                    error={errors.employee_id}
                    options={employees}
                />
            )}

            {assignedClients.length === 0 ? (
                <p className="border border-console-heading/30 px-4 py-3 text-sm text-console-heading">
                    This contractor has no approved client yet. Give them one on the{' '}
                    <Link href={route('admin.scheduling.clients.index')} className="underline hover:text-arka-teal">
                        Clients tab
                    </Link>
                    ; once the Super Admin approves it, you can schedule it here.
                </p>
            ) : (
                <SelectField
                    id="client_id"
                    label="Client"
                    value={data.client_id}
                    onChange={(e) => setData('client_id', e.target.value)}
                    error={errors.client_id}
                    options={assignedClients}
                />
            )}

            <fieldset>
                <Eyebrow>Working days</Eyebrow>
                <div className="mt-2 flex flex-wrap gap-1.5">
                    {weekdays.map((day) => {
                        const on = data.working_days.includes(day.value);
                        const blocked = !on && dayLimitReached;

                        return (
                            <button
                                key={day.value}
                                type="button"
                                aria-pressed={on}
                                disabled={blocked}
                                onClick={() => toggleDay(day.value)}
                                className={`w-12 border py-1.5 text-sm transition-colors disabled:cursor-not-allowed disabled:opacity-40 ${
                                    on ? 'border-arka-teal bg-arka-teal text-white' : 'border-console-line text-console-muted hover:border-arka-teal hover:text-arka-teal'
                                }`}
                            >
                                {day.label}
                            </button>
                        );
                    })}
                </div>
                <p className="mt-2 text-xs text-console-muted">
                    {data.working_days.length} of {MAX_WORKING_DAYS} days · a contractor works at most {MAX_WORKING_DAYS} days a week, across all their clients.
                </p>
                {errors.working_days && <p className="mt-2 text-xs text-console-error">{errors.working_days}</p>}
            </fieldset>

            <div className="grid grid-cols-2 gap-4">
                <Field id="start_time" type="time" label="Start time" value={data.start_time} onChange={(e) => setData('start_time', e.target.value)} error={errors.start_time} required />
                <Field
                    id="end_time"
                    type="time"
                    label="End time"
                    value={data.end_time}
                    onChange={(e) => setData('end_time', e.target.value)}
                    error={errors.end_time}
                    readOnly={Boolean(fixedHours)}
                    className={fixedHours ? 'opacity-70' : ''}
                    required
                />
            </div>
            {hours && (
                <p className="-mt-2 font-mono text-xs text-console-muted">
                    {fixedHours ? `${typeLabels[clientType]} client · ${fixedHours} hours a day (System & Rules)` : `Expected ${hours.hours}h per day`}
                    {hours.overnight ? ' · graveyard shift, ends the next day' : ''}
                </p>
            )}

            <div>
                <Field
                    id="break_allowance_minutes"
                    type="number"
                    min="0"
                    max="240"
                    label="Break allowance (minutes)"
                    value={data.break_allowance_minutes}
                    onChange={(e) => setData('break_allowance_minutes', e.target.value)}
                    error={errors.break_allowance_minutes}
                    required
                />
                <p className="mt-1.5 text-xs text-console-muted">Shown on the contractor's Time Tracker for awareness only; going over never reduces pay.</p>
            </div>

            <div className="grid grid-cols-2 gap-4">
                {changing ? (
                    <Field
                        id="effective_date"
                        type="date"
                        label="Effective from"
                        min={todayIso()}
                        value={data.effective_date}
                        onChange={(e) => setData('effective_date', e.target.value)}
                        error={errors.effective_date}
                        required
                    />
                ) : (
                    <Field id="start_date" type="date" label="Start date" min={todayIso()} value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} error={errors.start_date} required />
                )}
                <Field
                    id="end_date"
                    type="date"
                    label="End date (optional)"
                    min={(changing ? data.effective_date : data.start_date) || todayIso()}
                    value={data.end_date}
                    onChange={(e) => setData('end_date', e.target.value)}
                    error={errors.end_date}
                />
            </div>

            <div className="flex items-center gap-3">
                <ConsoleButton disabled={processing || assignedClients.length === 0}>Save schedule</ConsoleButton>
                <button type="button" onClick={onDone} className="text-sm text-console-muted hover:text-arka-teal">
                    Cancel
                </button>
            </div>
        </form>
    );
}

export default function Index({ schedules, filters, employees, clients, weekdays, hours: shiftHours, preselectEmployee, preselectClient }) {
    const { search, setSearch, apply } = useFilters('admin.scheduling.index', filters);
    const [form, setForm] = useState(preselectEmployee ? {} : null);
    const [toggling, setToggling] = useState(null);
    const [processing, setProcessing] = useState(false);

    const deactivating = toggling?.status === 'active';

    const toggleStatus = () =>
        router.patch(
            route('admin.scheduling.status', toggling.id),
            { status: deactivating ? 'inactive' : 'active' },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setToggling(null);
                },
            },
        );

    const rowButton = 'px-2 py-1 text-xs text-console-muted transition-colors hover:bg-console-raised hover:text-arka-teal';

    return (
        <AppLayout title="Schedules" eyebrow="Scheduling">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <SchedulingTabs />

                <Panel>
                    <PanelHeading
                        title="Schedule list"
                        subtitle="Working days and hours for each contractor at a client the Super Admin approved. Attendance is always compared with the schedule in effect on that date."
                        action={<ConsoleButton onClick={() => setForm({})}>Add schedule</ConsoleButton>}
                    />

                    <div className="mb-5 mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <div className="sm:min-w-[260px] sm:flex-1">
                            <SearchInput value={search} onChange={setSearch} placeholder="Search contractor or ID…" label="Search schedules" />
                        </div>
                        <select aria-label="Filter by client" value={filters.client} onChange={(e) => apply({ client: e.target.value })} className={filterSelect}>
                            <option value="">All clients</option>
                            {clients.map((client) => (
                                <option key={client.value} value={client.value}>
                                    {client.label}
                                </option>
                            ))}
                        </select>
                        <select aria-label="Filter by day" value={filters.day} onChange={(e) => apply({ day: e.target.value })} className={filterSelect}>
                            <option value="">Any day</option>
                            {weekdays.map((day) => (
                                <option key={day.value} value={day.value}>
                                    {day.label}
                                </option>
                            ))}
                        </select>
                        <select aria-label="Filter by status" value={filters.status} onChange={(e) => apply({ status: e.target.value })} className={filterSelect}>
                            <option value="current">Current</option>
                            <option value="active">Active only</option>
                            <option value="inactive">Inactive only</option>
                            <option value="ended">History (ended)</option>
                        </select>
                    </div>

                    <Table
                        columns={['Contractor', 'Client', 'Working days', 'Shift', 'In effect', 'Status', 'Actions']}
                        isEmpty={schedules.data.length === 0}
                        emptyMessage="No schedules match these filters."
                        minWidth={980}
                    >
                        {schedules.data.map((schedule) => (
                            <Row key={schedule.id}>
                                <Cell>
                                    <p className="font-medium text-console-heading">{schedule.employee.name}</p>
                                    <p className="font-mono text-xs text-console-dim">{schedule.employee.code}</p>
                                </Cell>
                                <Cell className="text-console-text">{schedule.client.name}</Cell>
                                <Cell>
                                    <WeekStrip days={schedule.workingDays} weekdays={weekdays} />
                                    <p className="mt-1 font-mono text-[11px] text-console-dim">{schedule.workingDays.length} days a week</p>
                                </Cell>
                                <Cell>
                                    <p className="whitespace-nowrap font-mono text-xs text-console-text">
                                        {clock(schedule.startTime)} – {clock(schedule.endTime)}
                                        {schedule.crossesMidnight && <span className="text-console-dim"> (+1 day)</span>}
                                    </p>
                                    <p className="mt-1 font-mono text-[11px] text-console-dim">
                                        {schedule.expectedHours}h · {schedule.breakAllowance} min break
                                    </p>
                                </Cell>
                                <Cell>
                                    <p className="whitespace-nowrap font-mono text-xs text-console-text">{fullDate(schedule.startDate)}</p>
                                    <p className="mt-1 whitespace-nowrap font-mono text-[11px] text-console-dim">
                                        {schedule.endDate ? `until ${fullDate(schedule.endDate)}` : 'no end date'}
                                    </p>
                                </Cell>
                                <Cell>
                                    <StatusBadge status={schedule.status} />
                                </Cell>
                                <td className="py-3 align-top">
                                    {schedule.status === 'ended' ? (
                                        <p className="text-right text-xs text-console-dim">History</p>
                                    ) : (
                                        <div className="flex justify-end gap-1">
                                            <button type="button" className={rowButton} onClick={() => setForm(schedule)}>
                                                Edit
                                            </button>
                                            <button type="button" className={rowButton} onClick={() => setToggling(schedule)}>
                                                {schedule.status === 'active' ? 'Deactivate' : 'Activate'}
                                            </button>
                                        </div>
                                    )}
                                </td>
                            </Row>
                        ))}
                    </Table>

                    <div className="mt-5">
                        <Pagination meta={schedules.meta} />
                    </div>
                </Panel>
            </div>

            <Dialog
                open={form !== null}
                onClose={() => setForm(null)}
                side
                title={form?.id ? 'Edit schedule' : 'Add schedule'}
                description={form?.id ? `${form.employee.name} · ${form.client.name}` : 'Working days and hours at one approved client'}
            >
                {form !== null && (
                    <ScheduleForm
                        key={form.id ?? 'new'}
                        schedule={form.id ? form : null}
                        employees={employees}
                        clients={clients}
                        weekdays={weekdays}
                        shiftHours={shiftHours}
                        preselectEmployee={preselectEmployee}
                        preselectClient={preselectClient}
                        onDone={() => setForm(null)}
                    />
                )}
            </Dialog>

            <ConfirmDialog
                open={toggling !== null}
                title={deactivating ? 'Deactivate schedule?' : 'Activate schedule?'}
                body={
                    deactivating
                        ? `${toggling?.employee.name}'s ${toggling?.client.name} schedule stops applying. It stays in history.`
                        : `${toggling?.employee.name}'s ${toggling?.client.name} schedule applies again.`
                }
                confirmLabel={deactivating ? 'Deactivate' : 'Activate'}
                danger={deactivating}
                processing={processing}
                onConfirm={toggleStatus}
                onClose={() => setToggling(null)}
            />
        </AppLayout>
    );
}
