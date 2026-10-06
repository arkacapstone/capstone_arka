import Dialog from '@/Components/Console/Dialog';
import Field, { ConsoleButton, SelectField } from '@/Components/Console/Field';
import Panel, { PanelHeading } from '@/Components/Console/Panel';
import StatusBadge from '@/Components/Console/StatusBadge';
import { PlusIcon } from '@/Components/Icons';
import PayrollOverviewCard, { FrequencyPicker } from '@/Components/Payroll/PayrollOverviewCard';
import { StageActions, periodTone } from '@/Components/Payroll/PayrollStages';
import VerificationResults from '@/Components/Payroll/VerificationResults';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import AppLayout from '@/Layouts/AppLayout';
import { dateRange, fullDate, peso } from '@/lib/format';
import { Link, router, useForm } from '@inertiajs/react';
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

export default function Index({ tab, frequency, current, upcoming, periods, verification, frequencies, suggested }) {
    const [creating, setCreating] = useState(false);
    // Payroll periods tab: '' = all pay frequencies.
    const [listFrequency, setListFrequency] = useState('');
    const currentPeriods = current ? periods.filter((period) => period.id === current.period.id) : [];
    const shownPeriods = listFrequency ? periods.filter((period) => period.frequencyValue === listFrequency) : periods;

    // The overview shows one pay frequency at a time.
    const frequencyPicker = {
        value: frequency,
        options: frequencies,
        onChange: (value) => router.get(route('super-admin.payroll'), value === 'semi_monthly' ? {} : { frequency: value }, { preserveScroll: true }),
    };
    const frequencyLabel = frequencies.find((option) => option.value === frequency)?.label ?? '';

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
                        {upcoming && (
                            // The current period has no payroll yet: it can still be started here, while the card below shows the latest payroll.
                            <Panel>
                                <PanelHeading
                                    title={`Current period · ${dateRange(upcoming.period.startDate, upcoming.period.endDate)}`}
                                    subtitle={`${upcoming.period.isProjected ? 'Not created yet' : upcoming.period.statusLabel} · no payroll calculated yet. Below is the latest period that has payroll.`}
                                />
                                <div className="mt-5">
                                    <StageActions overview={upcoming} />
                                </div>
                            </Panel>
                        )}

                        {current ? (
                            <>
                                <PayrollOverviewCard overview={current} frequencyPicker={frequencyPicker} />

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
                        ) : (
                            <Panel>
                                <PanelHeading
                                    title="Payroll overview"
                                    subtitle={
                                        <>
                                            No period yet
                                            <FrequencyPicker picker={frequencyPicker} />
                                        </>
                                    }
                                />
                                <p className="mt-6 text-sm text-console-muted">
                                    No {frequencyLabel.toLowerCase()} payroll period has been created yet. Create one with New period.
                                </p>
                            </Panel>
                        )}
                    </>
                )}

                {tab === 'periods' && (
                    <Panel>
                        <PanelHeading title="Payroll periods" subtitle="Every period, from open to released. Open one to review the payroll per contractor." />
                        <div className="mt-6 flex flex-wrap gap-2" role="group" aria-label="Filter by pay frequency">
                            {[{ value: '', label: 'All' }, ...frequencies].map((option) => {
                                const count = option.value ? periods.filter((period) => period.frequencyValue === option.value).length : periods.length;

                                return (
                                    <button
                                        key={option.value || 'all'}
                                        type="button"
                                        onClick={() => setListFrequency(option.value)}
                                        aria-pressed={listFrequency === option.value}
                                        className={`border px-4 py-2 text-sm font-medium transition-colors ${
                                            listFrequency === option.value
                                                ? 'border-arka-teal bg-arka-teal text-white'
                                                : 'border-console-line text-console-heading hover:border-arka-teal hover:text-arka-teal'
                                        }`}
                                    >
                                        {option.label} ({count})
                                    </button>
                                );
                            })}
                        </div>
                        <div className="mt-6">
                            <PeriodTable
                                periods={shownPeriods}
                                emptyMessage={
                                    periods.length === 0
                                        ? 'No payroll periods yet. Create one with New period.'
                                        : `No ${frequencies.find((option) => option.value === listFrequency)?.label.toLowerCase()} payroll periods.`
                                }
                            />
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
