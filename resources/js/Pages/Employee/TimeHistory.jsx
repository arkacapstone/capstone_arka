import { ConsoleButton } from '@/Components/Console/Field';
import Pagination from '@/Components/Console/Pagination';
import Panel, { Eyebrow, PanelHeading } from '@/Components/Console/Panel';
import DaySessions from '@/Components/Employee/DaySessions';
import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

const control =
    'rounded-none border border-console-line bg-console-panel py-2 pl-3 pr-9 text-sm text-console-text transition-colors hover:border-arka-aqua focus:border-arka-teal focus:ring-1 focus:ring-arka-teal';

export default function TimeHistory({ days, filters, clients, statuses }) {
    const [form, setForm] = useState(filters);

    const apply = (e) => {
        e.preventDefault();
        router.get(route('employee.time-history.index'), Object.fromEntries(Object.entries(form).filter(([, value]) => value !== '')), {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout title="Time history" eyebrow="Time tracker">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <Panel>
                    <PanelHeading
                        title="All days"
                        subtitle="Every day you tracked time. Open a day to see its sessions and anything that was fixed, with the old time."
                        action={
                            <p className="font-mono text-sm text-console-muted">
                                {days.meta.total} {days.meta.total === 1 ? 'day' : 'days'}
                            </p>
                        }
                    />

                    <form onSubmit={apply} className="mb-6 mt-6 flex flex-wrap items-end gap-3">
                        <label className="flex flex-col gap-1">
                            <Eyebrow>From</Eyebrow>
                            <input type="date" value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} className={control} />
                        </label>
                        <label className="flex flex-col gap-1">
                            <Eyebrow>To</Eyebrow>
                            <input type="date" value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} className={control} />
                        </label>
                        <label className="flex flex-col gap-1">
                            <Eyebrow>Client</Eyebrow>
                            <select value={form.client} onChange={(e) => setForm({ ...form, client: e.target.value })} className={control}>
                                <option value="">All clients</option>
                                {clients.map((client) => (
                                    <option key={client.value} value={client.value}>
                                        {client.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="flex flex-col gap-1">
                            <Eyebrow>Status</Eyebrow>
                            <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })} className={control}>
                                <option value="">All</option>
                                {statuses.map((status) => (
                                    <option key={status.value} value={status.value}>
                                        {status.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <ConsoleButton type="submit">Apply filters</ConsoleButton>
                        <Link href={route('employee.time-history.index')} className="px-2 py-2 text-sm text-console-muted hover:text-arka-teal">
                            Reset
                        </Link>
                    </form>

                    <div className="flex flex-col gap-2">
                        {days.data.length === 0 && <p className="py-6 text-sm italic text-console-muted">No sessions match these filters.</p>}
                        {days.data.map((day, index) => (
                            <DaySessions
                                key={day.date}
                                date={day.date}
                                sessions={day.sessions}
                                fixes={day.fixes}
                                defaultOpen={index === 0 && days.meta.current_page === 1}
                            />
                        ))}
                    </div>

                    <div className="mt-5">
                        <Pagination meta={days.meta} />
                    </div>
                </Panel>
            </div>
        </AppLayout>
    );
}
