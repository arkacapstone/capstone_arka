import Pagination from '@/Components/Console/Pagination';
import Panel, { PanelHeading } from '@/Components/Console/Panel';
import StatusBadge from '@/Components/Console/StatusBadge';
import StatTile from '@/Components/Workforce/StatTile';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import useFilters, { SearchInput } from '@/Components/Workforce/useFilters';
import WorkforceTabs from '@/Components/Workforce/WorkforceTabs';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { shortDate } from '@/lib/format';
import { Link } from '@inertiajs/react';

/** View-only: Admins add clients when they assign them; the Super Admin approves in Requests & Approvals. */
export default function Clients({ clients, filters, counts }) {
    const { search, setSearch, apply } = useFilters('super-admin.workforce.clients.index', filters);

    return (
        <SuperAdminLayout title="Workforce Management">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <WorkforceTabs />

                <div className="grid grid-cols-3 gap-4">
                    <StatTile label="All clients" value={counts.total} active={!filters.status} onClick={() => apply({ status: '' })} />
                    <StatTile label="Active" value={counts.active} active={filters.status === 'active'} onClick={() => apply({ status: 'active' })} />
                    <StatTile label="Inactive" value={counts.inactive} active={filters.status === 'inactive'} onClick={() => apply({ status: 'inactive' })} />
                </div>

                <Panel>
                    <PanelHeading
                        title="Clients"
                        subtitle="Admins add clients when they give them to contractors; each one appears here once you approve it. Each assignment carries its own rate."
                        action={
                            <Link href={route('super-admin.requests', { type: 'clients' })} className="text-sm text-arka-teal hover:underline">
                                Client assignments to approve →
                            </Link>
                        }
                    />

                    <div className="mb-5 mt-6">
                        <SearchInput value={search} onChange={setSearch} placeholder="Search client name or code…" label="Search clients" />
                    </div>

                    <Table
                        columns={['Client', 'Code', 'Assigned contractors', 'Status', 'Added']}
                        actions={false}
                        isEmpty={clients.data.length === 0}
                        emptyMessage={filters.search || filters.status ? 'No clients match these filters.' : 'No clients yet. They appear once you approve an Admin’s client assignment.'}
                    >
                        {clients.data.map((client) => (
                            <Row key={client.id}>
                                <Cell className="text-console-text">{client.name}</Cell>
                                <Cell className="text-console-muted">{client.code}</Cell>
                                <Cell>
                                    {client.assignedCount > 0 ? (
                                        <Link
                                            href={route('super-admin.workforce.employees.index', { client: client.id })}
                                            className="text-console-text transition-colors hover:text-arka-teal"
                                        >
                                            {client.assignedCount}
                                        </Link>
                                    ) : (
                                        <span className="text-console-dim">0</span>
                                    )}
                                </Cell>
                                <Cell>
                                    <StatusBadge status={client.status} />
                                </Cell>
                                <Cell className="text-console-muted">{client.createdAt ? shortDate(client.createdAt) : '—'}</Cell>
                            </Row>
                        ))}
                    </Table>

                    <div className="mt-5">
                        <Pagination meta={clients.meta} />
                    </div>
                </Panel>
            </div>
        </SuperAdminLayout>
    );
}
