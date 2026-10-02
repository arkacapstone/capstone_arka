import Dialog from '@/Components/Console/Dialog';
import { ConsoleButton } from '@/Components/Console/Field';
import { IssuedInvitation } from '@/Components/Console/Flash';
import Pagination from '@/Components/Console/Pagination';
import Panel, { PanelHeading } from '@/Components/Console/Panel';
import StatusBadge from '@/Components/Console/StatusBadge';
import AccountActionDialog, { AccountRowActions } from '@/Components/Workforce/AccountActionDialog';
import AccountForm from '@/Components/Workforce/AccountForm';
import StatTile from '@/Components/Workforce/StatTile';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import useFilters, { SearchInput } from '@/Components/Workforce/useFilters';
import WorkforceTabs from '@/Components/Workforce/WorkforceTabs';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { peso } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

const filterSelect =
    'rounded-none border border-console-line bg-console-panel py-2 pl-3 pr-9 text-[13px] text-console-text transition-colors hover:border-arka-teal focus:border-arka-teal focus:ring-0';

function Assignments({ assignments }) {
    if (!assignments?.length) {
        return <span className="text-[11px] text-console-heading">No client assigned</span>;
    }

    return (
        <ul className="flex flex-col gap-1">
            {assignments.map((rate) => (
                <li key={rate.id} className="text-xs">
                    <span className="text-console-text">{rate.client.name}</span>
                    <span className="text-console-dim">
                        {' '}
                        · {peso(rate.grossPay)} {rate.payFrequency === 'hourly' ? '/hr' : `· ${rate.payFrequencyLabel}`}
                    </span>
                </li>
            ))}
        </ul>
    );
}

export default function Employees({ employees, filters, counts, clients, employmentTypes }) {
    const { search, setSearch, apply } = useFilters('super-admin.workforce.employees.index', filters);
    const [formAccount, setFormAccount] = useState(null);
    const [pendingAction, setPendingAction] = useState(null);
    const filtered = filters.search || filters.status || filters.type || filters.client;

    return (
        <SuperAdminLayout title="Workforce Management">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <WorkforceTabs />

                <IssuedInvitation />

                <div className="grid grid-cols-2 gap-4 md:grid-cols-5">
                    <StatTile label="All" value={counts.total} active={!filters.status && !filters.type} onClick={() => apply({ status: '', type: '' })} />
                    <StatTile label="Active" value={counts.active} active={filters.status === 'active'} onClick={() => apply({ status: 'active' })} />
                    <StatTile label="Inactive" value={counts.inactive} active={filters.status === 'inactive'} onClick={() => apply({ status: 'inactive' })} />
                    <StatTile label="Full-Time" value={counts.fullTime} active={filters.type === 'full_time'} onClick={() => apply({ type: 'full_time' })} />
                    <StatTile label="Part-Time" value={counts.partTime} active={filters.type === 'part_time'} onClick={() => apply({ type: 'part_time' })} />
                </div>

                <Panel>
                    <PanelHeading
                        title="Contractors"
                        subtitle="Accounts, Full-Time / Part-Time classification, and the clients each person serves."
                        action={<ConsoleButton onClick={() => setFormAccount({})}>+ New contractor</ConsoleButton>}
                    />

                    <div className="mb-5 mt-6 flex flex-wrap gap-3">
                        <SearchInput value={search} onChange={setSearch} placeholder="Search name, email or code…" label="Search contractors" />
                        <select
                            aria-label="Filter by client"
                            value={filters.client}
                            onChange={(e) => apply({ client: e.target.value })}
                            className={filterSelect}
                        >
                            <option value="">All clients</option>
                            {clients.map((client) => (
                                <option key={client.id} value={client.id}>
                                    {client.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <Table
                        columns={['Name', 'Code', 'Type', 'Clients & rates', 'Status', 'Actions']}
                        isEmpty={employees.data.length === 0}
                        emptyMessage={filtered ? 'No contractors match these filters.' : 'No contractors yet. Create the first one.'}
                        minWidth={900}
                    >
                        {employees.data.map((account) => (
                            <Row key={account.id}>
                                <Cell>
                                    <Link
                                        href={route('super-admin.workforce.employees.show', account.id)}
                                        className="text-console-text transition-colors hover:text-arka-teal"
                                    >
                                        {account.name}
                                    </Link>
                                    <p className="mt-0.5 text-xs text-console-dim">{account.email}</p>
                                    {account.mustChangePassword && <p className="mt-1 text-[11px] text-console-heading">Temporary password not changed yet</p>}
                                </Cell>
                                <Cell className="text-console-muted">{account.employeeCode}</Cell>
                                <Cell className="text-console-muted">{account.employmentTypeLabel ?? <span className="text-console-heading">Not set</span>}</Cell>
                                <Cell>
                                    <Assignments assignments={account.assignments} />
                                </Cell>
                                <Cell>
                                    <StatusBadge status={account.invited ? 'invited' : account.status} />
                                </Cell>
                                <td className="py-3 align-top">
                                    <AccountRowActions account={account} onEdit={setFormAccount} onAction={setPendingAction}>
                                        <Link
                                            href={route('super-admin.workforce.employees.show', account.id)}
                                            className="px-2 py-1 text-xs text-arka-teal transition-colors hover:bg-console-raised hover:text-arka-teal"
                                        >
                                            Rates
                                        </Link>
                                    </AccountRowActions>
                                </td>
                            </Row>
                        ))}
                    </Table>

                    <div className="mt-5">
                        <Pagination meta={employees.meta} />
                    </div>
                </Panel>
            </div>

            <Dialog
                open={formAccount !== null}
                onClose={() => setFormAccount(null)}
                side
                title={formAccount?.id ? 'Edit contractor' : 'New contractor'}
                description={formAccount?.id ? formAccount.employeeCode : 'You will assign clients and rates next'}
            >
                {formAccount !== null && (
                    <AccountForm
                        key={formAccount.id ?? 'new'}
                        account={formAccount.id ? formAccount : null}
                        storeUrl={route('super-admin.workforce.employees.store')}
                        updateUrl={formAccount.id ? route('super-admin.workforce.employees.update', formAccount.id) : null}
                        submitLabel="Send invite"
                        onDone={() => setFormAccount(null)}
                    />
                )}
            </Dialog>

            <AccountActionDialog
                action={pendingAction}
                routePrefix="super-admin.workforce.employees"
                roleLabel="Contractor"
                onClose={() => setPendingAction(null)}
            />
        </SuperAdminLayout>
    );
}
