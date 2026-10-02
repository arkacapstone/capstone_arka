import Pagination from '@/Components/Console/Pagination';
import Panel, { Eyebrow, PanelHeading, ReversedBar } from '@/Components/Console/Panel';
import StatusBadge from '@/Components/Console/StatusBadge';
import { EyeIcon } from '@/Components/Icons';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import useFilters, { SearchInput } from '@/Components/Workforce/useFilters';
import AppLayout from '@/Layouts/AppLayout';
import { longDate, todayIso } from '@/lib/format';
import { Link } from '@inertiajs/react';

const control =
    'rounded-none border border-console-line bg-console-panel py-2 pl-3 pr-9 text-sm text-console-text transition-colors hover:border-arka-aqua focus:border-arka-teal focus:ring-1 focus:ring-arka-teal';

function submittedTime(iso) {
    return new Date(iso).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

export default function Index({ rows, filters, summary }) {
    const { search, setSearch, apply } = useFilters('admin.devotionals.index', filters);
    const percent = summary.expected ? Math.round((summary.submitted / summary.expected) * 100) : 0;
    const isToday = filters.date === todayIso();

    return (
        <AppLayout title="Devotionals" eyebrow="Devotional management">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <ReversedBar className="flex flex-wrap items-end justify-between gap-6">
                    <div>
                        <Eyebrow className="!text-white/70">{isToday ? 'Today' : 'Selected day'} · {longDate(filters.date)}</Eyebrow>
                        <p className="mt-2 font-mono text-4xl font-medium">
                            {summary.submitted} <span className="text-2xl text-white/60">of {summary.expected}</span>
                        </p>
                        <p className="mt-1 text-sm text-white/70">contractors submitted their devotional</p>
                    </div>
                    <div className="w-full max-w-sm">
                        <div className="h-1.5 w-full bg-white/20">
                            <div className="h-full bg-arka-aqua transition-all duration-500" style={{ width: `${percent}%` }} />
                        </div>
                        <p className="mt-2 text-xs text-white/70">For compliance monitoring only — no payroll deductions attached.</p>
                    </div>
                </ReversedBar>

                <Panel>
                    <PanelHeading title="Devotional list" subtitle="Uploaded before 12 midnight counts as on time; later uploads are flagged Late." />

                    <div className="mb-5 mt-6 flex flex-wrap gap-3">
                        <SearchInput value={search} onChange={setSearch} placeholder="Search contractor or ID…" label="Search contractors" />
                        <input type="date" aria-label="Date" value={filters.date} max={todayIso()} onChange={(e) => apply({ date: e.target.value })} className={control} />
                        <select aria-label="Filter by status" value={filters.status} onChange={(e) => apply({ status: e.target.value })} className={control}>
                            <option value="">All</option>
                            <option value="submitted">Submitted</option>
                            <option value="not_submitted">Not submitted</option>
                        </select>
                    </div>

                    <Table columns={['Contractor', 'Devotional title', 'Submitted at', 'Status', 'Actions']} isEmpty={rows.data.length === 0} emptyMessage="No contractors match these filters.">
                        {rows.data.map(({ employee, devotional }) => (
                            <Row key={employee.id}>
                                <Cell>
                                    <Link href={route('admin.devotionals.history', employee.id)} className="font-medium text-console-heading hover:text-arka-teal">
                                        {employee.name}
                                    </Link>
                                    <p className="font-mono text-xs text-console-dim">{employee.code}</p>
                                </Cell>
                                <Cell>{devotional?.title ?? <span className="text-console-dim">—</span>}</Cell>
                                <Cell className="font-mono text-xs">{devotional ? submittedTime(devotional.submittedAt) : '—'}</Cell>
                                <Cell>
                                    {devotional ? (
                                        <StatusBadge status="submitted" label={devotional.late ? 'Submitted · late' : 'Submitted'} />
                                    ) : (
                                        <StatusBadge status="not_submitted" label="Not submitted" />
                                    )}
                                </Cell>
                                <td className="py-3 align-top">
                                    <div className="flex justify-end gap-1">
                                        {devotional && (
                                            <a
                                                href={devotional.fileUrl}
                                                target="_blank"
                                                rel="noreferrer"
                                                title="View file (read-only)"
                                                className="inline-flex items-center gap-1 px-2 py-1 text-xs text-arka-teal hover:bg-console-raised"
                                            >
                                                <EyeIcon className="h-4 w-4" /> View
                                            </a>
                                        )}
                                        <Link
                                            href={route('admin.devotionals.history', employee.id)}
                                            className="px-2 py-1 text-xs text-console-muted hover:bg-console-raised hover:text-arka-teal"
                                        >
                                            History
                                        </Link>
                                    </div>
                                </td>
                            </Row>
                        ))}
                    </Table>

                    <div className="mt-5">
                        <Pagination meta={rows.meta} />
                    </div>
                </Panel>
            </div>
        </AppLayout>
    );
}
