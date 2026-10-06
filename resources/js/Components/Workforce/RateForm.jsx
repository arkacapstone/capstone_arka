import Field, { ConsoleButton, SelectField } from '@/Components/Console/Field';
import { peso } from '@/lib/format';
import { useForm } from '@inertiajs/react';

function today() {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

/**
 * Hourly Rate = Gross Pay ÷ (Working Days × Hours per Day); Daily Rate = Gross Pay ÷ Working Days (Blueprint §11).
 * For hourly arrangements the gross pay is already the hourly rate.
 */
function derivedRates({ gross_pay, working_days, hours_per_day }) {
    const gross = Number(gross_pay);
    const days = Number(working_days);
    const hours = Number(hours_per_day);

    if (!gross || !days || !hours) return null;

    return { hourly: gross / (days * hours), daily: gross / days };
}

/**
 * Sets the rate when approving an Admin's client assignment (`assignment`), or changes an existing
 * rate from an effective date (`rate`). New clients are only given by Admins, never assigned here.
 */
export default function RateForm({ employeeId, rate, assignment, defaults = {}, payFrequencies, onDone }) {
    const changing = Boolean(rate);
    const { data, setData, post, put, processing, errors } = useForm({
        gross_pay: rate?.grossPay ?? '',
        pay_frequency: rate?.payFrequency ?? 'semi_monthly',
        effective_date: assignment?.startDate ?? today(),
    });

    // Working days and hours are set only in System & Rules (hours follow the client's Full-Time /
    // Part-Time type); the server applies them. Here they only drive the hourly / daily preview.
    const type = assignment?.employmentType ?? rate?.employmentType;
    const preview = derivedRates({
        gross_pay: data.gross_pay,
        working_days: defaults.workingDays ?? 11,
        hours_per_day: { full_time: defaults.fullTimeHours, part_time: defaults.partTimeHours }[type] ?? defaults.hoursPerDay ?? 8,
    });

    const submit = (e) => {
        e.preventDefault();

        const options = { preserveScroll: true, onSuccess: onDone };

        changing
            ? put(route('super-admin.workforce.employees.rates.update', [employeeId, rate.id]), options)
            : post(route('super-admin.requests.clients.approve', assignment.id), options);
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-5">
            {changing ? (
                <div className="border border-console-line bg-console-panel px-4 py-3 text-xs leading-relaxed text-console-muted">
                    Current: <span className="text-console-text">{peso(rate.grossPay)}</span> · {rate.payFrequencyLabel}. The current rate
                    ends the day before the new effective date and stays in history.
                </div>
            ) : (
                <div className="border border-console-line bg-console-panel px-4 py-3 text-xs leading-relaxed text-console-muted">
                    <span className="text-console-text">{assignment.contractor.name}</span> → <span className="text-console-text">{assignment.client.name}</span>
                    {assignment.client.isNew && ' (new client, created on approval)'}
                    {assignment.employmentTypeLabel && ` · ${assignment.employmentTypeLabel}`}, given by
                    {' '}
                    {assignment.requestedBy}. Approving sets this rate; the Admin then schedules the client.
                    {(errors.client_id || errors.status) && <span className="mt-1 block text-console-error">{errors.client_id ?? errors.status}</span>}
                </div>
            )}

            <SelectField
                id="pay_frequency"
                label="Pay frequency"
                value={data.pay_frequency}
                onChange={(e) => setData('pay_frequency', e.target.value)}
                error={errors.pay_frequency}
                options={payFrequencies}
            />

            <Field
                id="gross_pay"
                type="number"
                min="1"
                step="0.01"
                label="Gross pay per period (₱)"
                value={data.gross_pay}
                onChange={(e) => setData('gross_pay', e.target.value)}
                error={errors.gross_pay}
                placeholder="20000.00"
                required
            />

            <Field
                id="effective_date"
                type="date"
                label="Effective date"
                value={data.effective_date}
                onChange={(e) => setData('effective_date', e.target.value)}
                error={errors.effective_date}
                className=""
                required
            />

            <dl className="grid grid-cols-2 border border-console-line">
                <div className="border-r border-console-line px-4 py-3">
                    <dt className="text-[11px] uppercase tracking-[0.2em] text-console-muted">Hourly rate</dt>
                    <dd className="mt-1 text-lg text-console-text">{preview ? peso(preview.hourly) : '—'}</dd>
                </div>
                <div className="px-4 py-3">
                    <dt className="text-[11px] uppercase tracking-[0.2em] text-console-muted">Daily rate</dt>
                    <dd className="mt-1 text-lg text-console-text">{preview ? peso(preview.daily) : '—'}</dd>
                </div>
            </dl>
            <p className="-mt-2 text-[11px] leading-relaxed text-console-dim">
                Hourly = gross ÷ (working days × hours per day). Daily = gross ÷ working days. Working days and hours per day are set in System &amp; Rules.
                Used for additional pay, absences and lates.
            </p>

            <div className="flex items-center gap-3">
                <ConsoleButton disabled={processing}>{changing ? 'Save new rate' : 'Approve and set rate'}</ConsoleButton>
                <button type="button" onClick={onDone} className="text-[13px] text-console-muted hover:text-arka-teal">
                    Cancel
                </button>
            </div>
        </form>
    );
}
