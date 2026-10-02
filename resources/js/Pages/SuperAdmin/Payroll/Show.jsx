import Dialog from '@/Components/Console/Dialog';
import Field, { ConsoleButton } from '@/Components/Console/Field';
import Panel, { MetricRow } from '@/Components/Console/Panel';
import StatusBadge from '@/Components/Console/StatusBadge';
import { ArrowLeftIcon } from '@/Components/Icons';
import { StageActions, Stepper, Totals, periodTone } from '@/Components/Payroll/PayrollStages';
import ConfirmDialog from '@/Components/Workforce/ConfirmDialog';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import AppLayout from '@/Layouts/AppLayout';
import { fullDate, peso } from '@/lib/format';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

function AdjustForm({ row, onDone }) {
    const { data, setData, patch, processing, errors } = useForm({
        additional_time: row.additionalTime,
        days_absent: row.daysAbsent,
        cash_advance_deduction: row.cashAdvance,
        other_deductions: row.other,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('super-admin.payroll.adjust', row.id), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    const number = (key) => ({
        type: 'number',
        step: '0.01',
        min: '0',
        value: data[key],
        onChange: (e) => setData(key, e.target.value),
        error: errors[key],
    });

    return (
        <form onSubmit={submit} className="flex flex-col gap-5">
            <div className="border-t border-console-line">
                <MetricRow label="Gross pay" value={peso(row.gross)} />
                <MetricRow label="Hourly rate" value={peso(row.hourlyRate)} />
                <MetricRow label="Daily rate" value={peso(row.dailyRate)} />
                <MetricRow label={`Absences (${row.daysAbsent} day${row.daysAbsent === 1 ? '' : 's'})`} value={`− ${peso(row.absence)}`} />
                <MetricRow label={`Late / undertime (${row.lateTime})`} value={`− ${peso(row.late)}`} />
                <MetricRow label={`Additional hours pay (${row.additionalTime})`} value={`+ ${peso(row.additionalPay)}`} />
                <MetricRow label="Overtime pay (approved tickets)" value={`+ ${peso(row.overtime)}`} />
                {row.reward > 0 && <MetricRow label="Rewards (additional pay)" value={`+ ${peso(row.reward)}`} />}
                <MetricRow label="Device" value={`− ${peso(row.device)}`} />
            </div>
            <p className="text-xs text-console-muted">
                Lates, absences and additional hours come from the locked attendance (additional hours = hours worked beyond working days × hours per day); overtime from approved tickets at the hourly rate. Change them here only to correct a mistake, e.g. half days.
            </p>
            <Field
                id="additional_time"
                label="Additional hours beyond expected (h:mm)"
                value={data.additional_time}
                onChange={(e) => setData('additional_time', e.target.value)}
                error={errors.additional_time}
                placeholder="18:20"
                inputMode="numeric"
            />
            <Field id="days_absent" label="Days absent (0.5 = half day)" {...number('days_absent')} step="0.5" />
            <Field id="cash_advance_deduction" label="Cash advance repayment (₱)" {...number('cash_advance_deduction')} />
            <Field id="other_deductions" label="Other approved deductions (₱)" {...number('other_deductions')} />
            <ConsoleButton type="submit" disabled={processing}>
                Save adjustments
            </ConsoleButton>
        </form>
    );
}

export default function Show({ overview, rows, canAdjust, canDelete, employeesPaid, verification }) {
    const { period, action, totals } = overview;
    const [adjusting, setAdjusting] = useState(null);
    const [deleting, setDeleting] = useState(false);

    return (
        <AppLayout title="Period" eyebrow="Payroll Management">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <div className="flex flex-wrap items-start justify-between gap-6">
                    <div>
                        <h2 className="font-condensed text-4xl font-bold text-console-heading">{period.name}</h2>
                        <div className="mt-2 flex flex-wrap items-center gap-3 text-sm text-console-muted">
                            <StatusBadge status={periodTone[period.status]} label={period.statusLabel} />
                            <span>{period.frequency}</span>
                            <span className="font-mono text-xs">
                                · cutoff {fullDate(period.cutoffDate)} · release {fullDate(period.releaseDate)}
                            </span>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href={route('super-admin.payroll')}
                            className="inline-flex items-center gap-2 border border-console-line px-4 py-2 text-sm font-medium text-console-heading transition-colors hover:border-arka-teal hover:text-arka-teal"
                        >
                            <ArrowLeftIcon className="h-4 w-4" /> All periods
                        </Link>
                        <StageActions overview={overview} showDescription={false} />
                    </div>
                </div>

                <Panel>
                    <Stepper steps={overview.steps} />
                    <Totals totals={totals} className="mt-8 border-b border-console-line pb-6" />

                    <div className="flex flex-col gap-3 py-5 text-sm text-console-muted">
                        {action.description ? (
                            <p>
                                <span className="font-semibold text-console-heading">Next step:</span> {action.description}
                            </p>
                        ) : (
                            <p>Payslips are released. This period is complete.</p>
                        )}
                        {period.status === 'verification' && (
                            <p>
                                <span className="font-semibold text-console-heading">
                                    {verification.verified} of {employeesPaid}
                                </span>{' '}
                                {employeesPaid === 1 ? 'contractor has' : 'contractors have'} submitted their attendance as verified · {verification.fixed} fixed at least one day.
                                Fixes are saved right away and appear in the attendance correction log.
                            </p>
                        )}
                        <p>
                            {rows.length === 0
                                ? `Payroll is calculated when attendance is locked (rates × attendance, late/undertime and absences). ${employeesPaid} ${employeesPaid === 1 ? 'person has' : 'people have'} a ${period.frequency.toLowerCase()} rate in this period.`
                                : canAdjust
                                  ? 'Review each row. Adjust additional hours, half-day absences, cash advance and other deductions before approving. Overtime comes from approved tickets.'
                                  : 'Payroll rows are approved and can no longer be adjusted.'}
                        </p>
                    </div>

                    <Table
                        columns={[
                            'Contractor',
                            'Gross',
                            'Additional',
                            'Absent',
                            'Late / UT',
                            'Cash adv.',
                            'Device',
                            'Other',
                            'Net pay',
                            'Status',
                            ...(canAdjust ? ['Action'] : []),
                        ]}
                        actions={canAdjust}
                        isEmpty={rows.length === 0}
                        emptyMessage="No payroll rows yet."
                        minWidth={1100}
                    >
                        {rows.map((row) => (
                            <Row key={row.id}>
                                <Cell>
                                    <p className="font-medium text-console-heading">{row.employee.name}</p>
                                    <p className="font-mono text-[11px] text-console-dim">
                                        {row.employee.code} · {row.client}
                                    </p>
                                </Cell>
                                <Cell className="font-mono">{peso(row.gross)}</Cell>
                                <Cell className="font-mono">{peso(row.additionalPay + row.overtime + row.reward)}</Cell>
                                <Cell className="font-mono">{peso(row.absence)}</Cell>
                                <Cell className="font-mono">{peso(row.late)}</Cell>
                                <Cell className="font-mono">{peso(row.cashAdvance)}</Cell>
                                <Cell className="font-mono">{peso(row.device)}</Cell>
                                <Cell className="font-mono">{peso(row.other)}</Cell>
                                <Cell className="font-mono font-medium text-console-heading">{peso(row.net)}</Cell>
                                <Cell>
                                    <StatusBadge status={row.status === 'draft' ? 'pending' : row.status === 'reviewed' ? 'pending' : 'approved'} label={row.status} />
                                </Cell>
                                {canAdjust && (
                                    <td className="py-3 text-right align-top">
                                        <button type="button" onClick={() => setAdjusting(row)} className="px-2 py-1 text-xs text-arka-teal hover:bg-console-raised">
                                            Adjust
                                        </button>
                                    </td>
                                )}
                            </Row>
                        ))}
                    </Table>

                    <p className="mt-6 text-xs text-console-dim">
                        Hourly rate = gross ÷ (working days × hours per day) · Daily rate = gross ÷ working days · Additional hours pay = hourly rate × hours beyond working
                        days × hours per day · Overtime pay = hourly rate × overtime hours · Net = gross pay + additional pay (other clients) + additional hours pay + overtime
                        pay − absences − late/undertime − cash advance (in full) − device − other deductions. On the payslip, the highest-paying client is Gross Pay; the others are Additional Pay.
                    </p>

                    {canDelete && (
                        <button type="button" onClick={() => setDeleting(true)} className="mt-4 text-xs text-console-muted hover:text-console-heading">
                            Delete this period
                        </button>
                    )}
                </Panel>
            </div>

            <Dialog
                open={adjusting !== null}
                onClose={() => setAdjusting(null)}
                side
                title="Adjust payroll row"
                description={adjusting ? `${adjusting.employee.name} · ${adjusting.client}` : ''}
            >
                {adjusting && <AdjustForm key={adjusting.id} row={adjusting} onDone={() => setAdjusting(null)} />}
            </Dialog>

            <ConfirmDialog
                open={deleting}
                title="Delete this payroll period?"
                body="The period has not been opened for verification, so nothing is lost. You can create it again later."
                confirmLabel="Delete period"
                danger
                onConfirm={() => router.delete(route('super-admin.payroll.destroy', period.id))}
                onClose={() => setDeleting(false)}
            />
        </AppLayout>
    );
}
