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
import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

const filterSelect =
    'rounded-none border border-console-line bg-console-panel py-2 pl-3 pr-9 text-sm text-console-text transition-colors hover:border-arka-aqua focus:border-arka-teal focus:ring-1 focus:ring-arka-teal';

export default function Index({ employees, filters, counts, employmentTypes }) {
    const { search, setSearch, apply } = useFilters('admin.employees.index', filters);
    const [formAccount, setFormAccount] = useState(null);
    const [pendingAction, setPendingAction] = useState(null);
    const filtered = filters.search || filters.status || filters.type;

    return (
        <AppLayout title="Contractors" eyebrow="Contractor management">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <IssuedInvitation />

                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <StatTile label="All contractors" value={counts.total} active={!filters.status} onClick={() => apply({ status: '' })} />
                    <StatTile label="Active" value={counts.active} active={filters.status === 'active'} onClick={() => apply({ status: 'active' })} />
                    <StatTile label="Inactive" value={counts.inactive} active={filters.status === 'inactive'} onClick={() => apply({ status: 'inactive' })} />
                    <StatTile label="New this week" value={counts.newThisWeek} />
                </div>

                <Panel>
                    <PanelHeading
                        title="Contractor list"
                        subtitle="Create accounts, keep profiles current, and activate or deactivate access."
                        action={<ConsoleButton onClick={() => setFormAccount({})}>Add contractor</ConsoleButton>}
                    />

                    <div className="mb-5 mt-6 flex flex-wrap gap-3">
                        <SearchInput value={search} onChange={setSearch} placeholder="Search name, contractor ID or email…" label="Search contractors" />
                        <select aria-label="Filter by status" value={filters.status} onChange={(e) => apply({ status: e.target.value })} className={filterSelect}>
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <select aria-label="Filter by type" value={filters.type} onChange={(e) => apply({ type: e.target.value })} className={filterSelect}>
                            <option value="">All types</option>
                            {employmentTypes.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <Table
                        columns={['Contractor ID', 'Full name', 'Role', 'Type', 'Account status', 'Actions']}
                        isEmpty={employees.data.length === 0}
                        emptyMessage={filtered ? 'No contractors match these filters.' : 'No contractors yet. Add the first one.'}
                        minWidth={880}
                    >
                        {employees.data.map((account) => (
                            <Row key={account.id}>
                                <Cell className="font-mono text-console-muted">{account.employeeCode}</Cell>
                                <Cell>
                                    <Link href={route('admin.employees.show', account.id)} className="font-medium text-console-heading hover:text-arka-teal">
                                        {account.name}
                                    </Link>
                                    <p className="mt-0.5 font-mono text-xs text-console-dim">{account.email}</p>
                                    {account.mustChangePassword && <p className="mt-1 text-xs text-console-heading">Waiting for first login</p>}
                                </Cell>
                                <Cell className="text-console-muted">Contractor</Cell>
                                <Cell className="text-console-muted">{account.employmentTypeLabel ?? '—'}</Cell>
                                <Cell>
                                    <StatusBadge status={account.invited ? 'invited' : account.status} />
                                </Cell>
                                <td className="py-3 align-top">
                                    <AccountRowActions account={account} onEdit={setFormAccount} onAction={setPendingAction}>
                                        <Link
                                            href={route('admin.employees.show', account.id)}
                                            className="px-2 py-1 text-xs text-arka-teal transition-colors hover:bg-console-raised"
                                        >
                                            View
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
                title={formAccount?.id ? 'Edit contractor' : 'Add contractor'}
                description={formAccount?.id ? formAccount.employeeCode : 'They get their login details by email'}
            >
                {formAccount !== null && (
                    <AccountForm
                        key={formAccount.id ?? 'new'}
                        account={formAccount.id ? formAccount : null}
                        storeUrl={route('admin.employees.store')}
                        updateUrl={formAccount.id ? route('admin.employees.update', formAccount.id) : null}
                        submitLabel="Send invite"
                        onDone={() => setFormAccount(null)}
                    />
                )}
            </Dialog>

            <AccountActionDialog action={pendingAction} routePrefix="admin.employees" roleLabel="Contractor" onClose={() => setPendingAction(null)} />
        </AppLayout>
    );
}
