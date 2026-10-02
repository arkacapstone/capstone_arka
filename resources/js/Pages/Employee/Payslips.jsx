import Dialog from '@/Components/Console/Dialog';
import { ConsoleButton, SecondaryButton } from '@/Components/Console/Field';
import Panel, { Eyebrow, PanelHeading, ReversedBar } from '@/Components/Console/Panel';
import StatusBadge from '@/Components/Console/StatusBadge';
import { DownloadIcon, FlagIcon } from '@/Components/Icons';
import PayslipDetail, { printPayslip } from '@/Components/Payslip/PayslipDetail';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import AppLayout from '@/Layouts/AppLayout';
import { dateRange, fullDate, peso } from '@/lib/format';
import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function FlagIssue({ payslip }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ note: '' });

    if (!open) {
        return (
            <button type="button" onClick={() => setOpen(true)} className="inline-flex items-center gap-1.5 text-sm text-arka-teal hover:underline print:hidden">
                <FlagIcon className="h-4 w-4" /> Flag an issue with this payslip
            </button>
        );
    }

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(route('employee.payslips.flag', payslip.periodId), {
                    preserveScroll: true,
                    onSuccess: () => {
                        reset();
                        setOpen(false);
                    },
                });
            }}
            className="w-full print:hidden"
        >
            <label htmlFor="note" className="block text-[11px] font-medium uppercase tracking-[0.18em] text-console-muted">
                What looks off?
            </label>
            <textarea
                id="note"
                rows={2}
                value={data.note}
                onChange={(e) => setData('note', e.target.value)}
                className="mt-2 block w-full rounded-none border border-console-line bg-console-panel px-3 py-2 text-sm text-console-text focus:border-arka-teal focus:ring-1 focus:ring-arka-teal"
                placeholder="e.g. My Northline hours look short for Sep 12."
                required
            />
            {errors.note && <p className="mt-1 text-xs text-console-error">{errors.note}</p>}
            <div className="mt-2 flex gap-3">
                <SecondaryButton type="submit" disabled={processing}>
                    Send to Super Admin
                </SecondaryButton>
                <button type="button" onClick={() => setOpen(false)} className="text-sm text-console-muted hover:text-arka-teal">
                    Cancel
                </button>
            </div>
        </form>
    );
}

export default function Payslips({ payslips, employee }) {
    const [open, setOpen] = useState(null);
    const latest = payslips.find((payslip) => payslip.status === 'available');

    // The dashboard's "View payslip" links here with ?open={periodId}.
    useEffect(() => {
        const requested = Number(new URLSearchParams(window.location.search).get('open'));
        const match = payslips.find((payslip) => payslip.periodId === requested && payslip.status === 'available');

        if (match) setOpen(match);
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <AppLayout title="My payslips" eyebrow="Payslip">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                {latest ? (
                    <ReversedBar className="flex flex-wrap items-end justify-between gap-6">
                        <div>
                            <Eyebrow className="!text-white/70">Latest payslip · {latest.period}</Eyebrow>
                            <p className="mt-1 font-mono text-sm text-white/70">{dateRange(latest.periodStart, latest.periodEnd)}</p>
                        </div>
                        <div>
                            <Eyebrow className="!text-white/70">Net pay</Eyebrow>
                            <p className="mt-1 font-mono text-5xl font-medium">{peso(latest.net)}</p>
                        </div>
                        <div className="flex gap-2">
                            <ConsoleButton onClick={() => setOpen(latest)}>View payslip</ConsoleButton>
                            <button
                                type="button"
                                onClick={() => {
                                    setOpen(latest);
                                    setTimeout(printPayslip, 150);
                                }}
                                className="inline-flex items-center gap-2 border border-white/40 px-4 py-2 text-sm font-medium text-white hover:border-white hover:bg-white/10"
                            >
                                <DownloadIcon className="h-4 w-4" /> Download PDF
                            </button>
                        </div>
                    </ReversedBar>
                ) : (
                    <ReversedBar>
                        <Eyebrow className="!text-white/70">Latest payslip</Eyebrow>
                        <p className="mt-2 font-condensed text-2xl font-bold">Nothing released yet</p>
                        <p className="mt-1 text-sm text-white/70">Your payslip appears here as soon as the Super Admin releases payroll.</p>
                    </ReversedBar>
                )}

                <Panel>
                    <PanelHeading title="Payslip history" subtitle="Every period, fully itemized. Government contributions are not deducted on ARKA payslips." />
                    <div className="mt-6">
                        <Table columns={['Pay period', 'Date issued', 'Net pay', 'Status', 'Action']} isEmpty={payslips.length === 0} emptyMessage="No payslips yet.">
                            {payslips.map((payslip) => (
                                <Row key={payslip.periodId}>
                                    <Cell>
                                        <p className="font-medium text-console-heading">{payslip.period}</p>
                                        <p className="font-mono text-xs text-console-muted">{dateRange(payslip.periodStart, payslip.periodEnd)}</p>
                                    </Cell>
                                    <Cell className="font-mono">{payslip.status === 'available' ? fullDate(payslip.issued) : '—'}</Cell>
                                    <Cell className="font-mono">{payslip.net !== null ? peso(payslip.net) : '—'}</Cell>
                                    <Cell>
                                        <StatusBadge status={payslip.status} label={payslip.status === 'available' ? 'Available' : payslip.status === 'on_hold' ? 'On hold' : 'Processing'} />
                                    </Cell>
                                    <td className="py-3 text-right align-top">
                                        {payslip.status === 'available' ? (
                                            <button type="button" onClick={() => setOpen(payslip)} className="px-2 py-1 text-xs text-arka-teal hover:bg-console-raised">
                                                View
                                            </button>
                                        ) : (
                                            <span className="px-2 text-xs text-console-dim">Not yet available</span>
                                        )}
                                    </td>
                                </Row>
                            ))}
                        </Table>
                    </div>
                </Panel>
            </div>

            <Dialog open={open !== null} onClose={() => setOpen(null)} wide title="Payslip" description={open?.period}>
                {open && <PayslipDetail payslip={open} employee={employee} actions={<FlagIssue payslip={open} />} />}
            </Dialog>
        </AppLayout>
    );
}
