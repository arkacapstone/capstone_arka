import Pagination from '@/Components/Console/Pagination';
import Panel, { Eyebrow, PanelHeading } from '@/Components/Console/Panel';
import StatusBadge from '@/Components/Console/StatusBadge';
import { ArrowLeftIcon, EyeIcon } from '@/Components/Icons';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import AppLayout from '@/Layouts/AppLayout';
import { fileSize, fullDate } from '@/lib/format';
import { Link } from '@inertiajs/react';

export default function History({ employee, devotionals, thisMonth, daysSoFar }) {
    const percent = daysSoFar ? Math.min(100, Math.round((thisMonth / daysSoFar) * 100)) : 0;

    return (
        <AppLayout title={employee.name} eyebrow="Devotional history">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <Link href={route('admin.devotionals.index')} className="-mb-3 inline-flex w-fit items-center gap-2 text-sm text-console-muted hover:text-arka-teal">
                    <ArrowLeftIcon className="h-4 w-4" /> All devotionals
                </Link>

                <Panel>
                    <Eyebrow>{employee.code}</Eyebrow>
                    <p className="mt-1 font-condensed text-3xl font-bold text-console-heading">{employee.name}</p>
                    <p className="mt-4 font-mono text-sm text-console-text">
                        Submitted {thisMonth} / {daysSoFar} days this month
                    </p>
                    <div className="mt-2 h-1.5 w-full max-w-md bg-console-track">
                        <div className="h-full bg-arka-aqua" style={{ width: `${percent}%` }} />
                    </div>
                </Panel>

                <Panel>
                    <PanelHeading title="Submissions" subtitle="Read-only. Admins view what was submitted and never edit it." />
                    <div className="mt-6">
                        <Table columns={['Date', 'Title', 'File', 'Size', 'Status', 'Actions']} isEmpty={devotionals.data.length === 0} emptyMessage="No devotionals submitted yet.">
                            {devotionals.data.map((devotional) => (
                                <Row key={devotional.id}>
                                    <Cell className="font-mono text-xs">{fullDate(devotional.date)}</Cell>
                                    <Cell className="font-medium text-console-heading">{devotional.title}</Cell>
                                    <Cell className="font-mono text-xs">{devotional.fileName}</Cell>
                                    <Cell className="font-mono text-xs">{fileSize(devotional.fileSize)}</Cell>
                                    <Cell>
                                        <StatusBadge status="submitted" label={devotional.late ? 'Submitted · late' : 'Submitted'} />
                                    </Cell>
                                    <td className="py-3 text-right align-top">
                                        <a
                                            href={devotional.fileUrl}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 px-2 py-1 text-xs text-arka-teal hover:bg-console-raised"
                                        >
                                            <EyeIcon className="h-4 w-4" /> View
                                        </a>
                                    </td>
                                </Row>
                            ))}
                        </Table>
                    </div>
                    <div className="mt-5">
                        <Pagination meta={devotionals.meta} />
                    </div>
                </Panel>
            </div>
        </AppLayout>
    );
}
