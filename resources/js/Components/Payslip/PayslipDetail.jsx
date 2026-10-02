import { ConsoleButton } from '@/Components/Console/Field';
import { Eyebrow } from '@/Components/Console/Panel';
import { DownloadIcon } from '@/Components/Icons';
import { dateRange, fullDate, peso } from '@/lib/format';

/** "Download as PDF": prints only the payslip (see the print rules in app.css). */
export function printPayslip() {
    document.body.classList.add('print-payslip');
    window.addEventListener('afterprint', () => document.body.classList.remove('print-payslip'), { once: true });
    window.print();
}

function Lines({ title, lines, total, totalLabel }) {
    return (
        <div>
            <Eyebrow>{title}</Eyebrow>
            <dl className="mt-3 border-t border-console-line text-sm">
                {lines.length === 0 && <div className="border-b border-console-line py-2 text-console-dim">— none this period —</div>}
                {lines.map((line) => (
                    <div key={line.label} className="flex justify-between gap-4 border-b border-console-line py-2">
                        <dt className="text-console-muted">
                            {line.label}
                            {line.detail && <span className="mt-0.5 block text-xs text-console-dim">{line.detail}</span>}
                        </dt>
                        <dd className="font-mono text-console-text">{peso(line.amount)}</dd>
                    </div>
                ))}
                <div className="flex justify-between gap-4 py-2.5 font-medium">
                    <dt className="text-console-heading">{totalLabel}</dt>
                    <dd className="font-mono text-console-heading">{peso(total)}</dd>
                </div>
            </dl>
        </div>
    );
}


/**
 * A fully itemized payslip: Gross Pay for the top-paying client, Additional Pay for the others, the approved ARKA deductions, and
 * net pay in the reversed navy field. `actions` sits beside the Download button.
 */
export default function PayslipDetail({ payslip, employee, actions = null, note = null }) {
    return (
        <div data-print-root className="bg-console-panel">
            <p className="hidden font-condensed text-2xl font-bold text-console-heading print:block">ARKA · Payslip</p>
            <p className="font-condensed text-2xl font-bold text-console-heading">{employee.name}</p>
            <p className="mt-1 font-mono text-xs text-console-muted">
                {employee.code}
                {employee.type ? ` · ${employee.type}` : ''} · Issued {fullDate(payslip.issued)}
            </p>
            <p className="mt-1 font-mono text-xs text-console-muted">
                {payslip.period} · {dateRange(payslip.periodStart, payslip.periodEnd)}
            </p>
            {note && <p className="mt-3 border border-console-heading/40 px-3 py-2 text-xs text-console-heading print:hidden">{note}</p>}

            <div className="mt-6 grid gap-6 sm:grid-cols-2">
                <Lines title="Earnings" lines={payslip.earnings} total={payslip.grossTotal} totalLabel="Total earnings" />
                <Lines title="Deductions" lines={payslip.deductions} total={payslip.deductionsTotal} totalLabel="Total deductions" />
            </div>

            <div className="mt-6 flex items-center justify-between bg-console-hero px-5 py-4 text-white">
                <span className="text-[11px] uppercase tracking-[0.2em] text-white/70">Net pay</span>
                <span className="font-mono text-3xl font-medium">{peso(payslip.net)}</span>
            </div>

            <div className="mt-6 flex flex-wrap items-start justify-between gap-4 print:hidden">
                <div>{actions}</div>
                <ConsoleButton onClick={printPayslip}>
                    <DownloadIcon className="h-4 w-4" /> Download as PDF
                </ConsoleButton>
            </div>
        </div>
    );
}
