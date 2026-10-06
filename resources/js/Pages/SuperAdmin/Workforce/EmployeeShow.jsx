import Dialog from '@/Components/Console/Dialog';
import { ConsoleButton } from '@/Components/Console/Field';
import { IssuedInvitation } from '@/Components/Console/Flash';
import Panel, { MetricRow, PanelHeading } from '@/Components/Console/Panel';
import StatusBadge from '@/Components/Console/StatusBadge';
import { ArrowRightIcon } from '@/Components/Icons';
import AccountActionDialog from '@/Components/Workforce/AccountActionDialog';
import AccountForm from '@/Components/Workforce/AccountForm';
import ConfirmDialog from '@/Components/Workforce/ConfirmDialog';
import RateForm from '@/Components/Workforce/RateForm';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import WorkforceTabs from '@/Components/Workforce/WorkforceTabs';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { peso, shortDate } from '@/lib/format';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

function fullDate(value) {
    return value ? new Date(`${value}T00:00:00`).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
}

function AssignmentCard({ rate, onChange, onEnd }) {
    return (
        <div className="border border-console-line p-5 transition-colors hover:border-arka-teal">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="font-condensed text-lg font-semibold text-console-text">{rate.client.name}</p>
                    <p className="text-xs text-console-dim">
                        {rate.client.code} · since {fullDate(rate.effectiveDate)}
                    </p>
                </div>
                <span className="border border-console-line px-2 py-0.5 text-[11px] text-console-muted">{rate.payFrequencyLabel}</span>
            </div>

            <p className="mt-4 font-mono text-2xl text-console-heading">
                {peso(rate.grossPay)}
                <span className="text-sm text-console-muted"> / period</span>
            </p>

            <dl className="mt-4 grid grid-cols-2 gap-x-6 gap-y-1.5 text-xs">
                {[
                    ['Hourly', peso(rate.hourlyRate)],
                    ['Daily', peso(rate.dailyRate)],
                ].map(([label, value]) => (
                    <div key={label}>
                        <dt className="text-console-dim">{label}</dt>
                        <dd className="font-mono text-console-text">{value}</dd>
                    </div>
                ))}
            </dl>

            <div className="mt-5 flex gap-2">
                <button
                    type="button"
                    onClick={() => onChange(rate)}
                    className="border border-console-line px-3 py-1.5 text-xs text-console-text transition-colors hover:border-arka-teal hover:bg-console-raised"
                >
                    Change rate
                </button>
                <button
                    type="button"
                    onClick={() => onEnd(rate)}
                    className="px-3 py-1.5 text-xs text-console-muted transition-colors hover:text-console-heading"
                >
                    End assignment
                </button>
            </div>
        </div>
    );
}

export default function EmployeeShow({ employee, section = 'employees', rateHistory, clients, employmentTypes, payFrequencies, rateDefaults }) {
    const [editing, setEditing] = useState(false);
    const [rateForm, setRateForm] = useState(null); // null = closed, rate = change
    const [ending, setEnding] = useState(null);
    const [endingProcessing, setEndingProcessing] = useState(false);
    const [accountAction, setAccountAction] = useState(null);

    const isAdmin = section === 'admins';
    const assignments = employee.assignments ?? [];

    const endAssignment = () =>
        router.delete(route('super-admin.workforce.employees.rates.destroy', [employee.id, ending.id]), {
            preserveScroll: true,
            onStart: () => setEndingProcessing(true),
            onFinish: () => {
                setEndingProcessing(false);
                setEnding(null);
            },
        });

    return (
        <SuperAdminLayout title="Workforce Management">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <WorkforceTabs />

                <Link
                    href={route(`super-admin.workforce.${section}.index`)}
                    className="-mb-4 inline-flex w-fit items-center gap-2 text-[13px] text-console-muted transition-colors hover:text-arka-teal"
                >
                    <ArrowRightIcon className="h-4 w-4 rotate-180" /> All {section}
                </Link>

                <IssuedInvitation />

                <section className="flex flex-wrap items-end justify-between gap-6 border border-console-line border-t-2 border-t-console-heading bg-console-panel px-6 py-7 sm:px-7">
                    <div>
                        <p className="text-[11px] uppercase tracking-[0.25em] text-console-muted">
                            {employee.employeeCode} · {employee.employmentTypeLabel ?? 'Type not set'}
                        </p>
                        <p className="mt-2 font-condensed text-4xl font-bold text-console-heading">{employee.name}</p>
                        <p className="mt-2 text-[13px] text-console-muted">{employee.email}</p>
                        <div className="mt-4 flex items-center gap-3">
                            <StatusBadge status={employee.invited ? 'invited' : employee.status} />
                            {employee.invited && (
                                <span className="text-xs text-console-heading">
                                    {employee.invitationExpired ? 'Invite link expired. Resend the invite.' : 'Waiting for them to verify their email'}
                                </span>
                            )}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <ConsoleButton type="button" onClick={() => setEditing(true)}>
                            Edit details
                        </ConsoleButton>
                        {employee.invited && (
                            <button
                                type="button"
                                onClick={() => setAccountAction({ type: 'invite', account: employee })}
                                className="border border-console-line px-4 py-2 text-[13px] text-console-text transition-colors hover:border-arka-teal"
                            >
                                Resend invite
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={() => setAccountAction({ type: 'status', account: employee })}
                            className={`border border-console-line px-4 py-2 text-[13px] transition-colors ${
                                employee.status === 'active' ? 'text-console-muted hover:border-console-mark hover:text-console-heading' : 'text-arka-teal hover:border-arka-teal'
                            }`}
                        >
                            {employee.status === 'active' ? 'Deactivate' : 'Activate'}
                        </button>
                    </div>
                </section>

                <div className="grid gap-9 xl:grid-cols-3 xl:gap-7">
                    <Panel className="xl:col-span-2">
                        <PanelHeading
                            title="Clients & rates"
                            subtitle="Admins give contractors their clients in Scheduling; you approve each one and set its rate in Requests & Approvals. One contractor may serve 2–3 clients, each with its own rate."
                            action={
                                <Link href={route('super-admin.requests', { type: 'clients' })} className="text-sm text-arka-teal hover:underline">
                                    Client assignments to approve →
                                </Link>
                            }
                        />

                        {assignments.length === 0 ? (
                            <p className="mt-7 font-condensed text-[15px] italic text-console-muted">
                                No client yet. Once an Admin gives this contractor a client and you approve it with a rate, they are included in payroll.
                            </p>
                        ) : (
                            <div className="mt-7 grid gap-4 lg:grid-cols-2">
                                {assignments.map((rate) => (
                                    <AssignmentCard key={rate.id} rate={rate} onChange={setRateForm} onEnd={setEnding} />
                                ))}
                            </div>
                        )}
                    </Panel>

                    <Panel className="self-start">
                        <PanelHeading title="Profile" subtitle="Account information" />
                        <div className="mt-7 border-t border-console-line">
                            <MetricRow label="Contractor code" value={employee.employeeCode} />
                            <MetricRow label="Type" value={employee.employmentTypeLabel ?? '—'} />
                            <MetricRow label="Phone" value={employee.phoneNumber ?? '—'} />
                            <MetricRow label="Birthday" value={employee.birthday ? fullDate(employee.birthday) : '—'} />
                            <MetricRow label="Age" value={employee.age ?? '—'} />
                            <MetricRow label="Address" value={employee.address ?? '—'} />
                            <MetricRow label="Emergency contact" value={employee.emergencyContactName ?? '—'} />
                            <MetricRow label="Emergency number" value={employee.emergencyContactNumber ?? '—'} />
                            <MetricRow label="Added" value={employee.createdAt ? shortDate(employee.createdAt) : '—'} />
                            <MetricRow label="Password" value={employee.mustChangePassword ? 'Temporary' : 'Set by user'} />
                            <MetricRow label="Profile" value={employee.profileCompleted ? 'Completed' : 'Waiting for them to fill in'} />
                        </div>
                    </Panel>
                </div>

                <Panel>
                    <PanelHeading title="Rate history" subtitle="Ended rates are kept so past payroll always uses the rate that applied." />
                    <div className="mt-6">
                        <Table
                            columns={['Client', 'Gross pay', 'Frequency', 'Hourly', 'Daily', 'Effective', 'Ended']}
                            actions={false}
                            isEmpty={rateHistory.data.length === 0}
                            emptyMessage="No previous rates."
                        >
                            {rateHistory.data.map((rate) => (
                                <Row key={rate.id}>
                                    <Cell className="text-console-text">{rate.client.name}</Cell>
                                    <Cell className="font-mono text-console-text">{peso(rate.grossPay)}</Cell>
                                    <Cell className="text-console-muted">{rate.payFrequencyLabel}</Cell>
                                    <Cell className="text-console-muted">{peso(rate.hourlyRate)}</Cell>
                                    <Cell className="text-console-muted">{peso(rate.dailyRate)}</Cell>
                                    <Cell className="text-console-muted">{fullDate(rate.effectiveDate)}</Cell>
                                    <Cell className="text-console-muted">{fullDate(rate.endDate)}</Cell>
                                </Row>
                            ))}
                        </Table>
                    </div>
                </Panel>
            </div>

            <Dialog open={editing} onClose={() => setEditing(false)} side title={isAdmin ? 'Edit admin' : 'Edit contractor'} description={employee.employeeCode}>
                {editing && (
                    <AccountForm
                        account={employee}
                        updateUrl={route(`super-admin.workforce.${section}.update`, employee.id)}
                        onDone={() => setEditing(false)}
                    />
                )}
            </Dialog>

            <Dialog
                open={rateForm !== null}
                onClose={() => setRateForm(null)}
                side
                title="Change rate"
                description={rateForm?.client.name}
            >
                {rateForm !== null && (
                    <RateForm
                        key={rateForm.id}
                        employeeId={employee.id}
                        rate={rateForm}
                        payFrequencies={payFrequencies}
                        defaults={rateDefaults}
                        onDone={() => setRateForm(null)}
                    />
                )}
            </Dialog>

            <ConfirmDialog
                open={ending !== null}
                title="End client assignment?"
                body={ending ? `${employee.name} will no longer be paid for ${ending.client.name} after today. The rate stays in history.` : ''}
                confirmLabel="End assignment"
                danger
                processing={endingProcessing}
                onConfirm={endAssignment}
                onClose={() => setEnding(null)}
            />

            <AccountActionDialog
                action={accountAction}
                routePrefix={`super-admin.workforce.${section}`}
                roleLabel={isAdmin ? 'Admin' : 'Contractor'}
                onClose={() => setAccountAction(null)}
            />
        </SuperAdminLayout>
    );
}
