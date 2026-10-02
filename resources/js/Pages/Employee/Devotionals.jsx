import Field, { ConsoleButton, SecondaryButton } from '@/Components/Console/Field';
import Pagination from '@/Components/Console/Pagination';
import Panel, { Eyebrow, PanelHeading, ReversedBar } from '@/Components/Console/Panel';
import { CircleCheckIcon, EyeIcon, UploadIcon } from '@/Components/Icons';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import AppLayout from '@/Layouts/AppLayout';
import { fileSize, fullDate, longDate } from '@/lib/format';
import { router, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';

const ACCEPT = '.pdf,.docx,.jpg,.jpeg,.png';

const control =
    'rounded-none border border-console-line bg-console-panel px-3 py-2 text-sm text-console-text transition-colors hover:border-arka-aqua focus:border-arka-teal focus:ring-1 focus:ring-arka-teal';

function submittedTime(iso) {
    return new Date(iso).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

function UploadCard({ today, replacing, onDone }) {
    const input = useRef(null);
    const [dragging, setDragging] = useState(false);
    const { data, setData, post, processing, errors, progress, reset } = useForm({ title: '', file: null });

    const pick = (file) => file && setData('file', file);

    const submit = (e) => {
        e.preventDefault();
        post(route('employee.devotionals.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onDone();
            },
        });
    };

    return (
        <Panel>
            <Eyebrow>Today · {longDate(today)}</Eyebrow>
            <h2 className="mt-2 font-condensed text-[22px] font-bold text-console-heading">{replacing ? 'Replace today’s devotional' : 'Today’s devotional'}</h2>

            <form onSubmit={submit} className="mt-6 flex flex-col gap-5">
                <Field id="title" label="Title" value={data.title} onChange={(e) => setData('title', e.target.value)} error={errors.title} placeholder="e.g. Psalm 23 reflection" required />

                <div>
                    <div
                        onDragOver={(e) => {
                            e.preventDefault();
                            setDragging(true);
                        }}
                        onDragLeave={() => setDragging(false)}
                        onDrop={(e) => {
                            e.preventDefault();
                            setDragging(false);
                            pick(e.dataTransfer.files[0]);
                        }}
                        className={`flex flex-col items-center justify-center gap-3 border border-dashed px-6 py-10 text-center transition-colors ${
                            dragging ? 'border-arka-teal bg-arka-teal/5' : errors.file ? 'border-console-error' : 'border-console-mark'
                        }`}
                    >
                        <UploadIcon className="h-7 w-7 text-arka-teal" />
                        {data.file ? (
                            <p className="font-mono text-sm text-console-heading">
                                {data.file.name} · {fileSize(data.file.size)}
                            </p>
                        ) : (
                            <p className="text-sm text-console-text">Drag your file here</p>
                        )}
                        <SecondaryButton onClick={() => input.current?.click()}>{data.file ? 'Choose another file' : 'Browse file'}</SecondaryButton>
                        <p className="font-mono text-[11px] text-console-dim">PDF, DOCX, JPG or PNG · up to 10 MB</p>
                        <input ref={input} type="file" accept={ACCEPT} className="hidden" onChange={(e) => pick(e.target.files[0])} />
                    </div>
                    {errors.file && <p className="mt-2 text-xs text-console-error">{errors.file}</p>}
                    {progress && (
                        <div className="mt-3 h-1 w-full bg-console-track">
                            <div className="h-full bg-arka-aqua" style={{ width: `${progress.percentage}%` }} />
                        </div>
                    )}
                </div>

                <ConsoleButton type="submit" disabled={processing || !data.file} className="w-full">
                    {replacing ? 'Replace devotional' : 'Submit devotional'}
                </ConsoleButton>
            </form>
        </Panel>
    );
}

function SubmissionStatus({ submission, onReplace }) {
    return (
        <Panel>
            <PanelHeading title="Submission status" />
            {submission ? (
                <div className="mt-6">
                    <p className="flex items-center gap-2 font-medium text-arka-teal">
                        <CircleCheckIcon className="h-5 w-5" /> Already submitted
                    </p>
                    <dl className="mt-5 border-t border-console-line text-sm">
                        {[
                            ['Title', submission.title],
                            ['File name', submission.fileName],
                            ['Submitted at', submittedTime(submission.submittedAt)],
                            ['File size', fileSize(submission.fileSize)],
                        ].map(([label, value]) => (
                            <div key={label} className="flex justify-between gap-4 border-b border-console-line py-2">
                                <dt className="text-console-muted">{label}</dt>
                                <dd className="truncate text-right font-mono text-[13px] text-console-text">{value}</dd>
                            </div>
                        ))}
                    </dl>
                    <div className="mt-5 flex gap-4 text-sm">
                        <a href={submission.fileUrl} target="_blank" rel="noreferrer" className="text-arka-teal hover:underline">
                            View file
                        </a>
                        <button type="button" onClick={onReplace} className="text-arka-teal hover:underline">
                            Replace
                        </button>
                    </div>
                </div>
            ) : (
                <div className="mt-6 border border-console-line px-4 py-5">
                    <p className="font-medium text-console-heading">Not submitted yet</p>
                    <p className="mt-1 text-sm text-console-muted">Today's devotional counts as on time until 12 midnight. Upload it whenever you're ready.</p>
                </div>
            )}
            <p className="mt-5 text-xs text-console-dim">Not submitted yet? You'd see a quiet reminder here — never a red alarm.</p>
        </Panel>
    );
}

export default function Devotionals({ today, submission, thisMonth, daysSoFar, records, filters }) {
    const [replacing, setReplacing] = useState(false);
    const [range, setRange] = useState(filters);
    const percent = daysSoFar ? Math.min(100, Math.round((thisMonth / daysSoFar) * 100)) : 0;
    const showUpload = !submission || replacing;

    const filter = (e) => {
        e.preventDefault();
        router.get(route('employee.devotionals.index'), Object.fromEntries(Object.entries(range).filter(([, value]) => value !== '')), { preserveScroll: true });
    };

    return (
        <AppLayout title="My devotionals" eyebrow="Devotional">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <ReversedBar className="flex flex-wrap items-end justify-between gap-6">
                    <div>
                        <Eyebrow className="!text-white/70">This month</Eyebrow>
                        <p className="mt-2 font-mono text-4xl font-medium">
                            Submitted {thisMonth} <span className="text-2xl text-white/60">/ {daysSoFar} days</span>
                        </p>
                    </div>
                    <div className="w-full max-w-sm">
                        <div className="h-1.5 w-full bg-white/20">
                            <div className="h-full bg-arka-aqua transition-all duration-500" style={{ width: `${percent}%` }} />
                        </div>
                        <p className="mt-2 text-xs text-white/70">For your own record — no deductions attached.</p>
                    </div>
                </ReversedBar>

                <div className="grid gap-9 lg:grid-cols-2 lg:gap-7">
                    {showUpload ? (
                        <UploadCard today={today} replacing={Boolean(submission)} onDone={() => setReplacing(false)} />
                    ) : (
                        <Panel>
                            <Eyebrow>Today · {longDate(today)}</Eyebrow>
                            <h2 className="mt-2 font-condensed text-[22px] font-bold text-console-heading">That's today done.</h2>
                            <p className="mt-2 text-sm text-console-muted">Thank you. Come back tomorrow, or replace today's file if you uploaded the wrong one.</p>
                        </Panel>
                    )}
                    <SubmissionStatus submission={submission} onReplace={() => setReplacing(true)} />
                </div>

                <Panel>
                    <PanelHeading
                        title="My devotional record"
                        action={
                            <form onSubmit={filter} className="flex flex-wrap items-center gap-2">
                                <input type="date" aria-label="From" value={range.from} onChange={(e) => setRange({ ...range, from: e.target.value })} className={control} />
                                <input type="date" aria-label="To" value={range.to} onChange={(e) => setRange({ ...range, to: e.target.value })} className={control} />
                                <SecondaryButton type="submit">Filter</SecondaryButton>
                            </form>
                        }
                    />
                    <div className="mt-6">
                        <Table columns={['Date', 'Title', 'File name', 'Submitted at', 'File size', 'Action']} isEmpty={records.data.length === 0} emptyMessage="No devotionals in this range yet.">
                            {records.data.map((record) => (
                                <Row key={record.id}>
                                    <Cell className="font-mono">{fullDate(record.date)}</Cell>
                                    <Cell className="font-medium text-console-heading">{record.title}</Cell>
                                    <Cell className="max-w-[220px] truncate text-console-muted">{record.fileName}</Cell>
                                    <Cell className="font-mono text-xs">
                                        {submittedTime(record.submittedAt)}
                                        {record.late && <span className="ml-2 text-console-muted">· late</span>}
                                    </Cell>
                                    <Cell className="font-mono text-xs">{fileSize(record.fileSize)}</Cell>
                                    <td className="py-3 text-right align-top">
                                        <a
                                            href={record.fileUrl}
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
                        <Pagination meta={records.meta} />
                    </div>
                </Panel>
            </div>
        </AppLayout>
    );
}
