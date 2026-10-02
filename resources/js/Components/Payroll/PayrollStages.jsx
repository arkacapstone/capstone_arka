import Dialog from '@/Components/Console/Dialog';
import { ConsoleButton, SecondaryButton } from '@/Components/Console/Field';
import { Eyebrow } from '@/Components/Console/Panel';
import { CheckIcon } from '@/Components/Icons';
import { peso } from '@/lib/format';
import { router } from '@inertiajs/react';
import { useState } from 'react';

/** Period open → Verification → Attendance lock → Review & approve → Released. */
export function Stepper({ steps }) {
    return (
        <ol className="relative mt-8 grid grid-cols-5">
            <div aria-hidden="true" className="absolute left-[10%] right-[10%] top-[14px] h-px bg-console-line" />
            {steps.map((step, index) => (
                <li key={step.key} className="relative flex flex-col items-center gap-2 text-center">
                    <span
                        className={`flex h-7 w-7 items-center justify-center rounded-full font-mono text-[11px] transition-transform duration-200 hover:scale-110 ${
                            step.state === 'complete'
                                ? 'bg-arka-teal text-white'
                                : step.state === 'current'
                                  ? 'bg-arka-navy text-white ring-4 ring-arka-aqua/25'
                                  : 'border border-console-line bg-console-panel text-console-muted'
                        }`}
                    >
                        {step.state === 'complete' ? <CheckIcon className="h-3.5 w-3.5" /> : index + 1}
                    </span>
                    <span className={`font-condensed text-sm sm:text-base ${step.state === 'current' ? 'font-semibold text-console-heading' : 'text-console-muted'}`}>
                        {step.label}
                    </span>
                </li>
            ))}
        </ol>
    );
}

export function Totals({ totals, className = '' }) {
    const figures = [
        ['Gross pay', totals.gross],
        ['Additions', totals.additions],
        ['Deductions', totals.deductions],
        ['Net pay', totals.net],
    ];

    return (
        <dl className={`grid grid-cols-2 gap-6 md:grid-cols-4 ${className}`}>
            {figures.map(([label, amount]) => (
                <div key={label}>
                    <dt>
                        <Eyebrow>{label}</Eyebrow>
                    </dt>
                    <dd className="mt-2 font-mono text-xl text-console-heading">{peso(amount)}</dd>
                </div>
            ))}
        </dl>
    );
}

/** Confirmation in the payroll style: title, what happens, Cancel + Confirm. */
function StageConfirm({ open, title, description, processing, onConfirm, onClose }) {
    return (
        <Dialog open={open} onClose={onClose} title={title} description={description}>
            <div className="flex justify-end gap-2">
                <SecondaryButton onClick={onClose} disabled={processing}>
                    Cancel
                </SecondaryButton>
                <ConsoleButton type="button" onClick={onConfirm} disabled={processing}>
                    {processing ? 'Working…' : 'Confirm'}
                </ConsoleButton>
            </div>
        </Dialog>
    );
}

/**
 * The period's next step (primary) and, for Verification and Locked, the toggle back (secondary).
 * A projected period (not saved yet) is created and opened for verification in one step.
 */
export function StageActions({ overview, showDescription = true }) {
    const { period, action } = overview;
    const [confirming, setConfirming] = useState(null); // 'advance' | 'revert'
    const [processing, setProcessing] = useState(false);

    const options = {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => {
            setProcessing(false);
            setConfirming(null);
        },
    };

    const run = () => {
        if (confirming === 'revert') {
            router.post(route('super-admin.payroll.revert', period.id), {}, options);
        } else if (period.isProjected) {
            router.post(
                route('super-admin.payroll.store'),
                {
                    start_date: period.startDate,
                    end_date: period.endDate,
                    cutoff_date: period.cutoffDate,
                    release_date: period.releaseDate,
                    pay_frequency: period.frequencyValue,
                    open_verification: true,
                },
                options,
            );
        } else {
            router.post(route('super-admin.payroll.advance', period.id), {}, options);
        }
    };

    if (!action.label && !action.revertLabel) {
        return showDescription ? <p className="text-sm text-console-muted">Payslips are released. This period is complete.</p> : null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-4">
            {showDescription && action.description && (
                <p className="text-sm text-console-muted">
                    <span className="font-semibold text-console-heading">Next step:</span> {action.description}
                </p>
            )}
            <div className="flex flex-wrap gap-2">
                {action.revertLabel && <SecondaryButton onClick={() => setConfirming('revert')}>{action.revertLabel}</SecondaryButton>}
                {action.label && <ConsoleButton onClick={() => setConfirming('advance')}>{action.label}</ConsoleButton>}
            </div>

            <StageConfirm
                open={confirming !== null}
                title={confirming === 'revert' ? action.revertLabel : action.label}
                description={confirming === 'revert' ? action.revertDescription : action.description}
                processing={processing}
                onConfirm={run}
                onClose={() => !processing && setConfirming(null)}
            />
        </div>
    );
}

/** Status tag tone for each period stage. */
export const periodTone = {
    open: 'not_started',
    verification: 'pending',
    locked: 'pending',
    processed: 'approved',
    released: 'completed',
};
