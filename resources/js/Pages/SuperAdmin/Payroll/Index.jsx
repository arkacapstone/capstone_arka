import Dialog from '@/Components/Console/Dialog';
import Field, { ConsoleButton, SelectField } from '@/Components/Console/Field';
import Panel, { PanelHeading } from '@/Components/Console/Panel';
import StatusBadge from '@/Components/Console/StatusBadge';
import { PlusIcon } from '@/Components/Icons';
import PayrollOverviewCard from '@/Components/Payroll/PayrollOverviewCard';
import { periodTone } from '@/Components/Payroll/PayrollStages';
import VerificationResults from '@/Components/Payroll/VerificationResults';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import AppLayout from '@/Layouts/AppLayout';
import { fullDate, peso } from '@/lib/format';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

function PeriodForm({ suggested, frequencies, onDone }) {
    const { data, setData, post, processing, errors } = useForm({
        period_name: '',
        start_date: suggested?.start_date ?? '',
        end_date: suggested?.end_date ?? '',
        cutoff_date: suggested?.cutoff_date ?? '',
        release_date: suggested?.release_date ?? '',
        pay_frequency: suggested?.pay_frequency ?? 'semi_monthly',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('super-admin.payroll.store'), { onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-5">
            <Field id="period_name" label="Name (optional)" value={data.period_name} onChange={(e) => setData('period_name', e.target.value)} error={errors.period_name} placeholder="Defaults to the date range" />
            <SelectField id="pay_frequency" label="Pay frequency" value={data.pay_frequency} onChange={(e) => setData('pay_frequency', e.target.value)} options={frequencies} error={errors.pay_frequency} />
            <div className="grid grid-cols-2 gap-4">
                <Field id="start_date" type="date" label="Start" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} error={errors.start_date} required />
                <Field id="end_date" type="date" label="End" value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} error={errors.end_date} required />
                <Field id="cutoff_date" type="date" label="Cutoff" value={data.cutoff_date} onChange={(e) => setData('cutoff_date', e.target.value)} error={errors.cutoff_date} required />
                <Field id="release_date" type="date" label="Release" value={data.release_date} onChange={(e) => setData('release_date', e.target.value)} error={errors.release_date} required />
            </div>
            <p className="text-xs text-console-muted">Payroll includes every active rate with this pay frequency. The default cycle is semi-monthly: cutoff on the 10th and 25th, release on the 15th and 30th.</p>
            <ConsoleButton type="submit" disabled={processing}>
                Create payroll period
            </ConsoleButton>
        </form>
    );
}

function PeriodTable({ periods, emptyMessage }) {
    return (
        <Table columns={['Period', 'Frequency', 'Cutoff', 'Release', 'Net pay', 'Status', 'Action']} isEmpty={periods.length === 0} emptyMessage={emptyMessage} minWidth={900}>
            {periods.map((period) => (
                <Row key={period.id}>
                    <Cell>
                        <Link href={route('super-admin.payroll.show', period.id)} className="font-medium text-console-heading hover:text-arka-teal">
                            {period.name}
                        </Link>
                    </Cell>
                    <Cell className="text-console-muted">{period.frequency}</Cell>
                    <Cell className="font-mono">{fullDate(period.cutoffDate)}</Cell>
                    <Cell className="font-mono">{fullDate(period.releaseDate)}</Cell>
                    <Cell className="font-mono">{peso(period.net)}</Cell>
                    <Cell>
                        <StatusBadge status={periodTone[period.status]} label={period.statusLabel} />
                    </Cell>
                    <td className="py-3 text-right align-top">
                        <Link href={route('super-admin.payroll.show', period.id)} className="px-2 py-1 text-xs text-arka-teal hover:bg-console-raised">
                            Open
                        </Link>
                    </td>
                </Row>
            ))}
        </Table>
    );
}

const tabs = [
    { key: 'overview', label: 'Payroll overview' },
    { key: 'periods', label: 'Payroll periods' },
    { key: 'verification', label: 'Payroll verified' },
];

export default function Index({ tab, current, periods, verification, frequencies, suggested }) {
    const [creating, setCreating] = useState(false);
    const currentPeriods = periods.filter((period) => period.id === current.period.id);

    return (
        <AppLayout title="Payroll Management" eyebrow="Payroll">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <div className="flex flex-wrap items-end justify-between gap-4 border-b border-console-line">
                    <nav className="flex gap-6 overflow-x-auto [scrollbar-width:none]" aria-label="Payroll sections">
                        {tabs.map((item) => (
                            <Link
                                key={item.key}
                                href={route('super-admin.payroll', item.key === 'overview' ? {} : { tab: item.key })}
                                preserveScroll
                                className={`-mb-px flex items-center gap-2 whitespace-nowrap border-b-2 pb-3 font-condensed text-[17px] font-semibold transition-colors ${
                                    tab === item.key ? 'border-arka-teal text-console-text' : 'border-transparent text-console-muted hover:text-arka-teal'
                                }`}
                            >
                                {item.label}
                                {item.key === 'verification' && verification.period?.fixWindowOpen && (
                                    <span className="h-2 w-2 rounded-full bg-arka-teal" aria-label="Verification is open" />
                                )}
                            </Link>
                        ))}
                    </nav>
                    <button
                        type="button"
                        onClick={() => setCreating(true)}
                        className="mb-3 inline-flex items-center gap-2 border border-console-line px-4 py-2 text-sm font-medium text-console-heading transition-colors hover:border-arka-teal hover:text-arka-teal"
                    >
                        <PlusIcon className="h-4 w-4" /> New period
                    </button>
                </div>

                {tab === 'overview' && (
                    <>
                        <PayrollOverviewCard overview={current} />

                        <Panel>
                            <PanelHeading title="Payroll in this overview" subtitle="The period shown above. Open it to review the payroll per contractor." />
                            <div className="mt-6">
                                <PeriodTable
                                    periods={currentPeriods}
                                    emptyMessage="This period is not created yet. Open verification from the overview above, or create it with New period."
                                />
                            </div>
                        </Panel>
                    </>
                )}

                {tab === 'periods' && (
                    <Panel>
                        <PanelHeading title="Payroll periods" subtitle="Every period, from open to released. Open one to review the payroll per contractor." />
                        <div className="mt-6">
                            <PeriodTable periods={periods} emptyMessage="No payroll periods yet. Create one with New period." />
                        </div>
                    </Panel>
                )}

                {tab === 'verification' && <VerificationResults verification={verification} />}
            </div>

            <Dialog open={creating} onClose={() => setCreating(false)} side title="New payroll period" description="Dates and pay frequency for this payout">
                {creating && <PeriodForm suggested={suggested} frequencies={frequencies} onDone={() => setCreating(false)} />}
            </Dialog>
        </AppLayout>
    );
}
