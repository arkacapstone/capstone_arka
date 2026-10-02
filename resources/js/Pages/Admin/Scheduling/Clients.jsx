import Dialog from '@/Components/Console/Dialog';
import Field, { ConsoleButton, SelectField } from '@/Components/Console/Field';
import Panel, { PanelHeading } from '@/Components/Console/Panel';
import { Tag } from '@/Components/Console/StatusBadge';
import { PlusIcon } from '@/Components/Icons';
import SchedulingTabs from '@/Components/Scheduling/SchedulingTabs';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import useFilters, { SearchInput } from '@/Components/Workforce/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import { fullDate, timeAgo, todayIso } from '@/lib/format';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const filterSelect =
    'rounded-none border border-console-line bg-console-panel py-2 pl-3 pr-9 text-sm text-console-text transition-colors hover:border-arka-aqua focus:border-arka-teal focus:ring-1 focus:ring-arka-teal';

const requestTone = { pending: 'waiting', approved: 'live', rejected: 'closed' };

function AssignForm({ contractors, clientNames, employmentTypes, hours, taken, onDone }) {
    const { data, setData, post, processing, errors } = useForm({
        employee_id: contractors[0]?.value ?? '',
        client_name: '',
        employment_type: employmentTypes[0]?.value ?? 'full_time',
        start_date: todayIso(),
    });

    const typed = data.client_name.trim().replace(/\s+/g, ' ').toLowerCase();
    const known = clientNames.some((name) => name.toLowerCase() === typed);
    const alreadyHas = typed !== '' && (taken[data.employee_id] ?? []).includes(typed);

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.scheduling.clients.store'), { preserveScroll: true, onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-5">
            <SelectField
                id="employee_id"
                label="Contractor"
                value={data.employee_id}
                onChange={(e) => setData('employee_id', e.target.value)}
                error={errors.employee_id}
                options={contractors}
            />

            <div>
                <Field
                    id="client_name"
                    label="Client name"
                    list="client-names"
                    autoComplete="off"
                    value={data.client_name}
                    onChange={(e) => setData('client_name', e.target.value)}
                    error={errors.client_name}
                    placeholder="e.g. Aurora Dental"
                    required
                />
                <datalist id="client-names">
                    {clientNames.map((name) => (
                        <option key={name} value={name} />
                    ))}
                </datalist>
                {typed !== '' && !errors.client_name && (
                    <p className={`mt-1.5 text-xs ${alreadyHas ? 'text-console-heading' : 'text-console-dim'}`}>
                        {alreadyHas
                            ? 'This contractor already has (or is waiting for) this client.'
                            : known
                              ? 'Existing client.'
                              : 'New client — it is added once the Super Admin approves.'}
                    </p>
                )}
            </div>

            <SelectField
                id="employment_type"
                label="Full-Time / Part-Time"
                value={data.employment_type}
                onChange={(e) => setData('employment_type', e.target.value)}
                error={errors.employment_type}
                options={employmentTypes.map((type) => ({ ...type, label: `${type.label} · ${hours[type.value]} hours a day` }))}
            />

            <Field
                id="start_date"
                type="date"
                label="Starts on"
                value={data.start_date}
                onChange={(e) => setData('start_date', e.target.value)}
                error={errors.start_date}
                required
            />

            <p className="text-xs leading-relaxed text-console-muted">
                A Full-Time client makes the contractor a Full-Time contractor. The Super Admin checks the assignment, sets the rate and approves it;
                then you can schedule it on the Schedules tab.
            </p>

            <div className="flex items-center gap-3">
                <ConsoleButton disabled={processing || alreadyHas}>Send for approval</ConsoleButton>
                <button type="button" onClick={onDone} className="text-sm text-console-muted hover:text-arka-teal">
                    Cancel
                </button>
            </div>
        </form>
    );
}

export default function Clients({ assignments, requests, filters, contractors, clients, clientNames, employmentTypes, hours, taken }) {
    const { search, setSearch, apply } = useFilters('admin.scheduling.clients.index', filters);
    const [assigning, setAssigning] = useState(false);

    const withdraw = (id) => router.post(route('admin.scheduling.clients.cancel', id), {}, { preserveScroll: true });

    return (
        <AppLayout title="Clients" eyebrow="Scheduling">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <SchedulingTabs />

                {requests.length > 0 && (
                    <Panel>
                        <PanelHeading
                            title="Waiting for the Super Admin"
                            subtitle="Clients you gave contractors. Decided ones stay here for two weeks so you can see the outcome."
                        />
                        <div className="mt-6">
                            <Table columns={['Contractor', 'Client', 'Starts', 'Sent', 'Status', 'Action']} isEmpty={false} minWidth={820}>
                                {requests.map((request) => (
                                    <Row key={request.id}>
                                        <Cell>
                                            <p className="font-medium text-console-heading">{request.contractor.name}</p>
                                            <p className="font-mono text-xs text-console-dim">{request.contractor.code}</p>
                                        </Cell>
                                        <Cell>
                                            {request.client}
                                            <p className="text-xs text-console-dim">
                                                {[request.type, request.isNewClient ? 'new client' : null].filter(Boolean).join(' · ')}
                                            </p>
                                        </Cell>
                                        <Cell className="font-mono">{fullDate(request.startDate)}</Cell>
                                        <Cell className="text-xs text-console-muted">
                                            {request.requestedBy}
                                            <span className="block font-mono text-console-dim">{timeAgo(request.submittedAt)}</span>
                                        </Cell>
                                        <Cell>
                                            <Tag tone={requestTone[request.status]}>{request.statusLabel}</Tag>
                                            {request.note && <p className="mt-1 max-w-xs text-xs text-console-muted">“{request.note}”</p>}
                                            {request.reviewer && <p className="mt-1 text-xs text-console-dim">by {request.reviewer}</p>}
                                        </Cell>
                                        <td className="py-3 text-right align-top">
                                            {request.status === 'pending' ? (
                                                <button
                                                    type="button"
                                                    onClick={() => withdraw(request.id)}
                                                    className="px-2 py-1 text-xs text-console-muted hover:bg-console-raised hover:text-arka-teal"
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
                )}

                <Panel>
                    <PanelHeading
                        title="Client assignments"
                        subtitle="Only Admins add clients. Each client is Full-Time or Part-Time for the contractor; a Full-Time client makes them a Full-Time contractor. The Super Admin approves it and sets the rate before it can be scheduled."
                        action={
                            <ConsoleButton onClick={() => setAssigning(true)}>
                                <PlusIcon className="h-4 w-4" /> Assign client
                            </ConsoleButton>
                        }
                    />

                    <div className="mb-5 mt-6 flex flex-wrap gap-3">
                        <SearchInput value={search} onChange={setSearch} placeholder="Search contractor or ID…" label="Search client assignments" />
                        <select aria-label="Filter by client" value={filters.client} onChange={(e) => apply({ client: e.target.value })} className={filterSelect}>
                            <option value="">All clients</option>
                            {clients.map((client) => (
                                <option key={client.value} value={client.value}>
                                    {client.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <Table
                        columns={['Contractor', 'Client', 'Type', 'Since', 'Schedules', 'Action']}
                        isEmpty={assignments.length === 0}
                        emptyMessage="No approved client assignments yet. Assign a client to get started."
                        minWidth={760}
                    >
                        {assignments.map((assignment) => (
                            <Row key={assignment.id}>
                                <Cell>
                                    <p className="font-medium text-console-heading">{assignment.contractor.name}</p>
                                    <p className="font-mono text-xs text-console-dim">
                                        {assignment.contractor.code}
                                        {assignment.contractor.type ? ` · ${assignment.contractor.type}` : ''}
                                    </p>
                                </Cell>
                                <Cell>
                                    <p className="text-console-heading">{assignment.client.name}</p>
                                    <p className="font-mono text-xs text-console-dim">{assignment.client.code}</p>
                                </Cell>
                                <Cell>{assignment.type ? <Tag tone={assignment.type === 'Full-Time' ? 'live' : 'waiting'}>{assignment.type}</Tag> : '—'}</Cell>
                                <Cell className="font-mono">{fullDate(assignment.since)}</Cell>
                                <Cell>
                                    {assignment.schedules > 0 ? (
                                        <Tag tone="live">{assignment.schedules} active</Tag>
                                    ) : (
                                        <Tag tone="waiting">Not scheduled yet</Tag>
                                    )}
                                </Cell>
                                <td className="py-3 text-right align-top">
                                    <Link
                                        href={route('admin.scheduling.index', { employee: assignment.contractor.id, client: assignment.client.id })}
                                        className="px-2 py-1 text-xs text-arka-teal hover:bg-console-raised"
                                    >
                                        {assignment.schedules > 0 ? 'View schedules' : 'Add schedule'}
                                    </Link>
                                </td>
                            </Row>
                        ))}
                    </Table>
                </Panel>
            </div>

            <Dialog open={assigning} onClose={() => setAssigning(false)} side title="Assign client" description="Type the client and choose Full-Time or Part-Time. The Super Admin approves it.">
                {assigning && (
                    <AssignForm
                        contractors={contractors}
                        clientNames={clientNames}
                        employmentTypes={employmentTypes}
                        hours={hours}
                        taken={taken}
                        onDone={() => setAssigning(false)}
                    />
                )}
            </Dialog>
        </AppLayout>
    );
}
