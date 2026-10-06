import { SecondaryButton } from '@/Components/Console/Field';
import Pagination from '@/Components/Console/Pagination';
import Panel, { Eyebrow, PanelHeading } from '@/Components/Console/Panel';
import { Tag } from '@/Components/Console/StatusBadge';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import useFilters, { SearchInput } from '@/Components/Workforce/useFilters';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { timeAgo } from '@/lib/format';
import { router } from '@inertiajs/react';

const control =
    'rounded-none border border-console-line bg-console-panel py-2 pl-3 pr-9 text-sm text-console-text transition-colors hover:border-arka-aqua focus:border-arka-teal focus:ring-1 focus:ring-arka-teal';

function when(iso) {
    return new Date(iso).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

function Figure({ label, value }) {
    return (
        <div className="border border-console-line px-5 py-4">
            <Eyebrow>{label}</Eyebrow>
            <p className="mt-2 font-mono text-2xl font-medium text-console-heading">{value}</p>
        </div>
    );
}

export default function Index({ logs, filters, modules, users, summary }) {
    const { search, setSearch, apply } = useFilters('super-admin.activity-logs', filters);
    const filtered = ['module', 'user', 'search', 'from', 'to'].some((key) => filters[key] !== '' && filters[key] !== null);

    return (
        <SuperAdminLayout title="Activity Logs">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <div className="grid gap-4 sm:grid-cols-3">
                    <Figure label="Actions today" value={summary.today} />
                    <Figure label="Sign-ins today" value={summary.loginsToday} />
                    <Figure label="Actions this week" value={summary.thisWeek} />
                </div>

                <Panel>
                    <PanelHeading
                        title="Activity logs"
                        subtitle="Sign-ins, account, attendance and payroll changes, approvals and other important actions. Records cannot be edited or deleted."
                    />

                    <div className="mt-6 flex flex-wrap items-end gap-3">
                        <label className="flex flex-col gap-1">
                            <Eyebrow>Module</Eyebrow>
                            <select value={filters.module} onChange={(e) => apply({ module: e.target.value })} className={control}>
                                <option value="">All modules</option>
                                {modules.map((module) => (
                                    <option key={module.value} value={module.value}>
                                        {module.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="flex flex-col gap-1">
                            <Eyebrow>Done by</Eyebrow>
                            <select value={filters.user} onChange={(e) => apply({ user: e.target.value })} className={control}>
                                <option value="">Anyone</option>
                                {users.map((user) => (
                                    <option key={user.value} value={user.value}>
                                        {user.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="flex flex-col gap-1">
                            <Eyebrow>From</Eyebrow>
                            <input type="date" value={filters.from} onChange={(e) => apply({ from: e.target.value })} className={control} />
                        </label>
                        <label className="flex flex-col gap-1">
                            <Eyebrow>To</Eyebrow>
                            <input type="date" value={filters.to} onChange={(e) => apply({ to: e.target.value })} className={control} />
                        </label>
                        <div className="min-w-64 flex-1">
                            <SearchInput value={search} onChange={setSearch} placeholder="Search actions and details" label="Search activity logs" />
                        </div>
                        {filtered && (
                            <SecondaryButton onClick={() => router.get(route('super-admin.activity-logs'), {}, { preserveScroll: true })}>Clear filters</SecondaryButton>
                        )}
                    </div>

                    <div className="mt-6">
                        <Table
                            columns={['When', 'Module', 'Action', 'Details', 'Done by']}
                            actions={false}
                            isEmpty={logs.data.length === 0}
                            emptyMessage={filtered ? 'No activity matches these filters.' : 'No activity recorded yet.'}
                            minWidth={1080}
                        >
                            {logs.data.map((log) => (
                                <Row key={log.id}>
                                    <Cell className="whitespace-nowrap font-mono text-xs">
                                        {when(log.createdAt)}
                                        <span className="block text-console-dim">{timeAgo(log.createdAt)}</span>
                                    </Cell>
                                    <Cell>
                                        <Tag tone={log.module === 'auth' ? 'closed' : 'waiting'}>{log.moduleLabel}</Tag>
                                    </Cell>
                                    <Cell className="font-medium text-console-heading">{log.action}</Cell>
                                    <Cell className="max-w-md text-console-muted">{log.details ?? '—'}</Cell>
                                    <Cell>
                                        {log.user ? (
                                            <>
                                                <p className="text-console-heading">{log.user.name}</p>
                                                <p className="font-mono text-xs text-console-dim">
                                                    {log.user.code ? `${log.user.code} · ` : ''}
                                                    {log.user.role}
                                                </p>
                                            </>
                                        ) : (
                                            <span className="text-console-muted">System</span>
                                        )}
                                    </Cell>
                                </Row>
                            ))}
                        </Table>
                    </div>

                    <div className="mt-5">
                        <Pagination meta={logs.meta} />
                    </div>
                </Panel>
            </div>
        </SuperAdminLayout>
    );
}
