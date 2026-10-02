import Dialog from '@/Components/Console/Dialog';
import Panel, { PanelHeading } from '@/Components/Console/Panel';
import StatusBadge from '@/Components/Console/StatusBadge';
import { ArrowRightIcon, EyeIcon } from '@/Components/Icons';
import PayslipDetail from '@/Components/Payslip/PayslipDetail';
import StatTile from '@/Components/Workforce/StatTile';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import useFilters, { SearchInput } from '@/Components/Workforce/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import { fullDate, peso } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

const control =
    'rounded-none border border-console-line bg-console-panel py-2 pl-3 pr-9 text-sm text-console-text transition-colors hover:border-arka-aqua focus:border-arka-teal focus:ring-1 focus:ring-arka-teal';

export default function Index({ periods, period, payslips, summary, filters }) {
    const { search, setSearch, apply } = useFilters('super-admin.payslips', filters);
    const [open, setOpen] = useState(null);

    return (
        <AppLayout title="Payslips" eyebrow="Payslip records">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                {!period ? (
                    <Panel>
                        <PanelHeading
                            title="No payslips yet"
                            subtitle="Payslips are generated from payroll. Lock attendance and calculate payroll for a period first."
                            action={
                                <Link href={route('super-admin.payroll')} className="group inline-flex items-center gap-2 text-sm font-medium text-arka-teal">
                                    Go to Payroll Management <ArrowRightIcon className="h-4 w-4 transition-transform group-hover:translate-x-1" />
                                </Link>
                            }
                        />
                    </Panel>
                ) : (
                    <>
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <StatTile label="Payslips" value={summary.count} />
                            <StatTile label="Gross pay" value={peso(summary.gross)} />
                            <StatTile label="Net pay" value={peso(summary.net)} />
                            <StatTile label="Status" value={period.released ? 'Released' : 'Preview'} active={period.released} />
                        </div>

                        <Panel>
                            <PanelHeading
                                title={period.name}
                                subtitle={
                                    period.released
                                        ? `Released · employees can see these payslips (release date ${fullDate(period.releaseDate)}).`
                                        : `${period.statusLabel} · a preview. Contractors see their payslips once the period is released.`
                                }
                                action={
                                    <Link href={route('super-admin.payroll.show', period.id)} className="group inline-flex items-center gap-2 text-sm font-medium text-arka-teal">
                                        Open payroll period <ArrowRightIcon className="h-4 w-4 transition-transform group-hover:translate-x-1" />
                                    </Link>
                                }
                            />

                            <div className="mb-5 mt-6 flex flex-wrap gap-3">
                                <select aria-label="Payroll period" value={filters.period} onChange={(e) => apply({ period: e.target.value, search: '' })} className={control}>
                                    {periods.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <SearchInput value={search} onChange={setSearch} placeholder="Search contractor or ID…" label="Search payslips" />
                            </div>

                            <Table
                                columns={['Contractor', 'Clients', 'Gross', 'Deductions', 'Net pay', 'Status', 'Action']}
                                isEmpty={payslips.length === 0}
                                emptyMessage={filters.search ? 'No payslips match this search.' : 'No payslips in this period.'}
                                minWidth={900}
                            >
                                {payslips.map((payslip) => (
                                    <Row key={payslip.employee.id}>
                                        <Cell>
                                            <p className="font-medium text-console-heading">{payslip.employee.name}</p>
                                            <p className="font-mono text-[11px] text-console-dim">
                                                {payslip.employee.code} · {payslip.employee.role}
                                            </p>
                                        </Cell>
                                        <Cell className="text-console-muted">{payslip.clients.join(', ') || '—'}</Cell>
                                        <Cell className="font-mono">{peso(payslip.grossTotal)}</Cell>
                                        <Cell className="font-mono">{peso(payslip.deductionsTotal)}</Cell>
                                        <Cell className="font-mono font-medium text-console-heading">{peso(payslip.net)}</Cell>
                                        <Cell>
                                            <StatusBadge status={payslip.status} label={payslip.status === 'available' ? 'Released' : 'Not released'} />
                                        </Cell>
                                        <td className="py-3 text-right align-top">
                                            <button type="button" onClick={() => setOpen(payslip)} className="inline-flex items-center gap-1 px-2 py-1 text-xs text-arka-teal hover:bg-console-raised">
                                                <EyeIcon className="h-4 w-4" /> View
                                            </button>
                                        </td>
                                    </Row>
                                ))}
                            </Table>
                        </Panel>
                    </>
                )}
            </div>

            <Dialog open={open !== null} onClose={() => setOpen(null)} wide title="Payslip" description={open?.period}>
                {open && (
                    <PayslipDetail
                        payslip={open}
                        employee={open.employee}
                        note={open.status === 'available' ? null : 'Preview — this payslip is not released yet, so the contractor cannot see it.'}
                    />
                )}
            </Dialog>
        </AppLayout>
    );
}
