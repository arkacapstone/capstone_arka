import { SecondaryButton } from '@/Components/Console/Field';
import Panel, { Eyebrow, PanelHeading } from '@/Components/Console/Panel';
import { ArrowLeftIcon, DownloadIcon, PrinterIcon } from '@/Components/Icons';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import AppLayout from '@/Layouts/AppLayout';
import { fullDate } from '@/lib/format';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

const control =
    'rounded-none border border-console-line bg-console-panel py-2 pl-3 pr-9 text-sm text-console-text transition-colors hover:border-arka-aqua focus:border-arka-teal focus:ring-1 focus:ring-arka-teal';

function ReportTable({ columns, rows, emptyMessage }) {
    return (
        <Table columns={columns} actions={false} isEmpty={rows.length === 0} emptyMessage={emptyMessage} minWidth={640}>
            {rows.map((row, index) => (
                <Row key={index}>
                    {row.map((value, cell) => (
                        <Cell key={cell} className={cell === 0 ? 'font-medium text-console-heading' : typeof value === 'number' ? 'font-mono' : ''}>
                            {value ?? '—'}
                        </Cell>
                    ))}
                </Row>
            ))}
        </Table>
    );
}

export default function Show({
    report,
    filters,
    summary,
    details,
    employees,
    statusOptions,
    generatedAt,
    routes = { index: 'admin.reports.index', show: 'admin.reports.show' },
}) {
    const [form, setForm] = useState({
        from: filters.from,
        to: filters.to,
        employee: filters.employee ?? '',
        status: filters.status ?? '',
    });

    const query = Object.fromEntries(Object.entries(form).filter(([, value]) => value !== ''));

    const generate = (e) => {
        e.preventDefault();
        router.get(route(routes.show, report.key), query, { preserveScroll: true });
    };

    return (
        <AppLayout title={report.title} eyebrow="Reports">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <Link href={route(routes.index)} className="-mb-3 inline-flex w-fit items-center gap-2 text-sm text-console-muted hover:text-arka-teal print:hidden">
                    <ArrowLeftIcon className="h-4 w-4" /> All reports
                </Link>

                <form onSubmit={generate} className="flex flex-wrap items-end gap-3 print:hidden">
                    <label className="flex flex-col gap-1">
                        <Eyebrow>Contractor</Eyebrow>
                        <select value={form.employee} onChange={(e) => setForm({ ...form, employee: e.target.value })} className={control}>
                            <option value="">All contractors</option>
                            {employees.map((employee) => (
                                <option key={employee.value} value={employee.value}>
                                    {employee.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="flex flex-col gap-1">
                        <Eyebrow>From</Eyebrow>
                        <input type="date" value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} className={control} />
                    </label>
                    <label className="flex flex-col gap-1">
                        <Eyebrow>To</Eyebrow>
                        <input type="date" value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} className={control} />
                    </label>
                    {statusOptions.length > 0 && (
                        <label className="flex flex-col gap-1">
                            <Eyebrow>{report.key === 'leave' ? 'Leave type' : report.key === 'workforce' ? 'Account status' : 'Status'}</Eyebrow>
                            <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })} className={control}>
                                <option value="">All</option>
                                {statusOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                    <button type="submit" className="border border-arka-teal bg-arka-teal px-4 py-2 text-sm font-medium text-white hover:bg-[#276E82]">
                        Generate report
                    </button>
                    <div className="ml-auto flex gap-2">
                        <SecondaryButton onClick={() => window.print()}>
                            <PrinterIcon className="h-4 w-4" /> Print / PDF
                        </SecondaryButton>
                        <a
                            href={route(routes.show, { report: report.key, ...query, export: 'csv' })}
                            className="inline-flex items-center gap-2 border border-console-line px-4 py-2 text-sm font-medium text-console-heading hover:border-arka-teal hover:text-arka-teal"
                        >
                            <DownloadIcon className="h-4 w-4" /> CSV
                        </a>
                    </div>
                </form>

                <div className="hidden print:block">
                    <p className="font-condensed text-3xl font-bold text-console-heading">{report.title}</p>
                    <p className="font-mono text-sm">
                        {fullDate(filters.from)} – {fullDate(filters.to)} · Generated {new Date(generatedAt).toLocaleString()}
                    </p>
                </div>

                <Panel>
                    <PanelHeading title="Summary" subtitle={`${fullDate(filters.from)} – ${fullDate(filters.to)}`} />
                    <div className="mt-6">
                        <ReportTable columns={summary.columns} rows={summary.rows} emptyMessage="No records in this period." />
                    </div>
                </Panel>

                <Panel>
                    <PanelHeading title="Details" subtitle={`${details.rows.length} row${details.rows.length === 1 ? '' : 's'}`} />
                    <div className="mt-6">
                        <ReportTable columns={details.columns} rows={details.rows} emptyMessage="No records in this period." />
                    </div>
                </Panel>
            </div>
        </AppLayout>
    );
}
