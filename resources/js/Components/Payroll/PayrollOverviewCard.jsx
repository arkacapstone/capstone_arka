import Panel, { PanelHeading } from '@/Components/Console/Panel';
import { ArrowRightIcon } from '@/Components/Icons';
import { dateRange, shortDate } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { StageActions, Stepper, Totals } from './PayrollStages';

/**
 * Choose which pay frequency the overview shows (Weekly, Semi-monthly, Monthly).
 * `picker` = { value, options, onChange }.
 */
export function FrequencyPicker({ picker }) {
    return (
        <select
            aria-label="Pay frequency"
            value={picker.value}
            onChange={(e) => picker.onChange(e.target.value)}
            className="ml-2 border border-arka-teal bg-transparent py-1 pl-3 pr-8 align-middle text-sm font-medium text-console-heading transition-colors hover:bg-arka-teal/10 focus:border-arka-teal focus:ring-1 focus:ring-arka-teal"
        >
            {picker.options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}

/** The current payroll period: where it stands, what it pays out, and its next step. */
export default function PayrollOverviewCard({ overview, showOpenLink = true, frequencyPicker = null }) {
    const { period, totals, records } = overview;

    return (
        <Panel>
            <PanelHeading
                title="Payroll overview"
                subtitle={
                    frequencyPicker ? (
                        <>
                            {dateRange(period.startDate, period.endDate)}
                            <FrequencyPicker picker={frequencyPicker} />
                        </>
                    ) : (
                        `${dateRange(period.startDate, period.endDate)} · ${period.frequency}`
                    )
                }
                action={
                    <span className="border border-console-heading/40 px-2.5 py-1 font-mono text-xs text-console-heading">
                        Cutoff {shortDate(period.cutoffDate)} · Release {shortDate(period.releaseDate)}
                    </span>
                }
            />

            <Stepper steps={overview.steps} />

            <Totals totals={totals} className="mt-8 border-b border-console-line pb-6" />

            <div className="flex flex-wrap items-center justify-between gap-4 border-b border-console-line py-5 text-sm text-console-muted">
                <p>
                    {records.total === 0
                        ? 'Payroll has not been calculated for this period yet. It is calculated when attendance is locked.'
                        : `${records.total} payroll record${records.total === 1 ? '' : 's'} · ${records.byStatus.draft} draft · ${records.byStatus.reviewed} reviewed · ${records.byStatus.approved} approved · ${records.byStatus.released} released`}
                </p>
                {showOpenLink && !period.isProjected && (
                    <Link href={route('super-admin.payroll.show', period.id)} className="group inline-flex items-center gap-2 font-medium text-arka-teal">
                        Open period
                        <ArrowRightIcon className="h-4 w-4 transition-transform group-hover:translate-x-1" />
                    </Link>
                )}
            </div>

            <div className="pt-5">
                <StageActions overview={overview} />
            </div>
        </Panel>
    );
}
