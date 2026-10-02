import Dialog from '@/Components/Console/Dialog';
import { ConsoleButton, SecondaryButton } from '@/Components/Console/Field';
import { IssuedInvitation } from '@/Components/Console/Flash';
import Panel, { Eyebrow, MetricRow, PanelHeading } from '@/Components/Console/Panel';
import StatusBadge from '@/Components/Console/StatusBadge';
import { ArrowLeftIcon } from '@/Components/Icons';
import AccountActionDialog from '@/Components/Workforce/AccountActionDialog';
import AccountForm from '@/Components/Workforce/AccountForm';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import AppLayout from '@/Layouts/AppLayout';
import { clock, fullDate } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

export default function Show({ employee, clients, schedules, attendanceMonth, employmentTypes }) {
    const [editing, setEditing] = useState(false);
    const [accountAction, setAccountAction] = useState(null);

    return (
        <AppLayout title={employee.name} eyebrow="Contractor management">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <Link href={route('admin.employees.index')} className="-mb-3 inline-flex w-fit items-center gap-2 text-sm text-console-muted hover:text-arka-teal">
                    <ArrowLeftIcon className="h-4 w-4" /> All contractors
                </Link>

                <IssuedInvitation />

                <section className="flex flex-wrap items-end justify-between gap-6 border border-console-line border-t-2 border-t-console-heading bg-console-panel px-6 py-7 sm:px-7">
                    <div>
                        <Eyebrow>
                            {employee.employeeCode} · {employee.employmentTypeLabel ?? 'Type not set'}
                        </Eyebrow>
                        <p className="mt-2 font-condensed text-4xl font-bold text-console-heading">{employee.name}</p>
                        <p className="mt-1 font-mono text-sm text-console-muted">{employee.email}</p>
                        <div className="mt-4 flex items-center gap-3">
                            <StatusBadge status={employee.invited ? 'invited' : employee.status} />
                            {employee.invited ? (
                                <span className="text-xs text-console-heading">
                                    {employee.invitationExpired ? 'Invite link expired. Resend the invite.' : 'Waiting for them to verify their email'}
                                </span>
                            ) : employee.mustChangePassword ? (
                                <span className="text-xs text-console-heading">Waiting for first login</span>
                            ) : (
                                !employee.profileCompleted && <span className="text-xs text-console-heading">Waiting for them to complete their profile</span>
                            )}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <ConsoleButton type="button" onClick={() => setEditing(true)}>
                            Edit contractor
                        </ConsoleButton>
                        {employee.invited && (
                            <SecondaryButton onClick={() => setAccountAction({ type: 'invite', account: employee })}>Resend invite</SecondaryButton>
                        )}
                        <SecondaryButton onClick={() => setAccountAction({ type: 'status', account: employee })}>
                            {employee.status === 'active' ? 'Deactivate' : 'Activate'}
                        </SecondaryButton>
                    </div>
                </section>

                <div className="grid gap-9 xl:grid-cols-3 xl:gap-7">
                    <Panel>
                        <PanelHeading title="Personal information" />
                        <div className="mt-6 border-t border-console-line">
                            <MetricRow label="Full name" value={employee.name} />
                            <MetricRow label="Birthday" value={fullDate(employee.birthday)} />
                            <MetricRow label="Age" value={employee.age ?? '—'} />
                            <MetricRow label="Phone" value={employee.phoneNumber ?? '—'} />
                            <MetricRow label="Email" value={employee.email} />
                            <MetricRow label="Address" value={employee.address ?? '—'} />
                            <MetricRow label="Emergency contact" value={employee.emergencyContactName ?? '—'} />
                            <MetricRow label="Emergency number" value={employee.emergencyContactNumber ?? '—'} />
                        </div>
                    </Panel>

                    <Panel>
                        <PanelHeading title="Employment information" />
                        <div className="mt-6 border-t border-console-line">
                            <MetricRow label="Contractor ID" value={employee.employeeCode} />
                            <MetricRow label="Role" value="Contractor" />
                            <MetricRow label="Type" value={employee.employmentTypeLabel ?? '—'} />
                            <MetricRow label="Account status" value={employee.status} />
                            <MetricRow label="Added" value={fullDate(employee.createdAt)} />
                        </div>
                        <p className="mt-4 text-xs text-console-muted">
                            Clients: {clients.length > 0 ? clients.join(', ') : 'none yet — the Super Admin assigns clients and rates.'}
                        </p>
                    </Panel>

                    <Panel>
                        <PanelHeading
                            title="Attendance summary"
                            subtitle={attendanceMonth.label}
                            action={
                                <Link href={route('admin.attendance.index', { employee: employee.id })} className="text-sm font-medium text-arka-teal hover:underline">
                                    Records
                                </Link>
                            }
                        />
                        <div className="mt-6 border-t border-console-line">
                            {attendanceMonth.counts.map((row) => (
                                <MetricRow key={row.key} label={row.label} value={row.count} />
                            ))}
                        </div>
                    </Panel>
                </div>

                <Panel>
                    <PanelHeading
                        title="Schedules"
                        subtitle="Current schedules first; earlier schedules stay as history."
                        action={
                            <Link
                                href={route('admin.scheduling.index', { employee: employee.id })}
                                className="border border-console-line px-4 py-2 text-sm font-medium text-console-heading transition-colors hover:border-arka-teal hover:text-arka-teal"
                            >
                                Manage schedules
                            </Link>
                        }
                    />
                    <div className="mt-6">
                        <Table
                            columns={['Client', 'Job position', 'Working days', 'Time', 'Break', 'Period', 'Status']}
                            actions={false}
                            isEmpty={schedules.length === 0}
                            emptyMessage="No schedules yet."
                            minWidth={860}
                        >
                            {schedules.map((schedule) => (
                                <Row key={schedule.id}>
                                    <Cell className="font-medium text-console-heading">{schedule.client.name}</Cell>
                                    <Cell>{schedule.jobPosition}</Cell>
                                    <Cell className="font-mono text-xs uppercase">{schedule.workingDays.join(' ')}</Cell>
                                    <Cell className="font-mono text-xs">
                                        {clock(schedule.startTime)} – {clock(schedule.endTime)}
                                    </Cell>
                                    <Cell className="font-mono">{schedule.breakAllowance} min</Cell>
                                    <Cell className="font-mono text-xs">
                                        {fullDate(schedule.startDate)} – {schedule.endDate ? fullDate(schedule.endDate) : 'ongoing'}
                                    </Cell>
                                    <Cell>
                                        <StatusBadge status={schedule.status} />
                                    </Cell>
                                </Row>
                            ))}
                        </Table>
                    </div>
                </Panel>
            </div>

            <Dialog open={editing} onClose={() => setEditing(false)} side title="Edit contractor" description={employee.employeeCode}>
                {editing && (
                    <AccountForm
                        account={employee}
                        updateUrl={route('admin.employees.update', employee.id)}
                        onDone={() => setEditing(false)}
                    />
                )}
            </Dialog>

            <AccountActionDialog action={accountAction} routePrefix="admin.employees" roleLabel="Contractor" onClose={() => setAccountAction(null)} />
        </AppLayout>
    );
}
