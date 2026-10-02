import { Eyebrow } from '@/Components/Console/Panel';
import VerificationResults from '@/Components/Payroll/VerificationResults';
import AppLayout from '@/Layouts/AppLayout';
import { usePoll } from '@inertiajs/react';

const steps = [
    ['1', 'Contractors fix & submit', 'When the Super Admin opens verification, each contractor gets one chance to fix their days, then submits.'],
    ['2', 'You review & remind', 'Check every change below. Send a reminder to anyone who has not submitted yet.'],
    ['3', 'You submit last', 'Submit the verified period. The Super Admin can then process payroll.'],
];

/** Admin → Period Verification: review the contractors' changes and submit the verified period. */
export default function Index({ verification }) {
    usePoll(60_000, { only: ['verification', 'notifications'] });

    return (
        <AppLayout title="Period Verification">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <ol className="grid gap-4 md:grid-cols-3">
                    {steps.map(([number, title, body]) => (
                        <li key={number} className="flex gap-3 border border-console-line px-5 py-4">
                            <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-arka-teal font-mono text-[11px] text-white">{number}</span>
                            <div>
                                <Eyebrow>{title}</Eyebrow>
                                <p className="mt-1 text-sm text-console-muted">{body}</p>
                            </div>
                        </li>
                    ))}
                </ol>

                <VerificationResults verification={verification} mode="admin" />
            </div>
        </AppLayout>
    );
}
