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
import { shortDate } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

export default function Admins({ admins, filters, counts }) {
    const { search, setSearch, apply } = useFilters('super-admin.workforce.admins.index', filters);
    const [formAccount, setFormAccount] = useState(null); // null = closed, {} = create, account = edit
    const [pendingAction, setPendingAction] = useState(null);

    return (
        <SuperAdminLayout title="Workforce Management">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <WorkforceTabs />

                <IssuedInvitation />

                <div className="grid grid-cols-3 gap-4">
                    <StatTile label="All admins" value={counts.total} active={!filters.status} onClick={() => apply({ status: '' })} />
                    <StatTile label="Active" value={counts.active} active={filters.status === 'active'} onClick={() => apply({ status: 'active' })} />
                    <StatTile label="Inactive" value={counts.inactive} active={filters.status === 'inactive'} onClick={() => apply({ status: 'inactive' })} />
                </div>

                <Panel>
                    <PanelHeading
                        title="Admin accounts"
                        subtitle="Admins run day-to-day operations. They never see payroll or other money-related modules."
                        action={<ConsoleButton onClick={() => setFormAccount({})}>+ New admin</ConsoleButton>}
                    />

                    <div className="mb-5 mt-6">
                        <SearchInput value={search} onChange={setSearch} placeholder="Search name, email or code…" label="Search admins" />
                    </div>

                    <Table
                        columns={['Name', 'Code', 'Phone', 'Status', 'Added', 'Actions']}
                        isEmpty={admins.data.length === 0}
                        emptyMessage={filters.search || filters.status ? 'No admins match these filters.' : 'No admins yet. Create the first one.'}
                    >
                        {admins.data.map((account) => (
                            <Row key={account.id}>
                                <Cell>
                                    <Link href={route('super-admin.workforce.employees.show', account.id)} className="text-console-text hover:text-arka-teal">
                                        {account.name}
                                    </Link>
                                    <p className="mt-0.5 text-xs text-console-dim">{account.email}</p>
                                    {account.mustChangePassword && <p className="mt-1 text-[11px] text-console-heading">Temporary password not changed yet</p>}
                                </Cell>
                                <Cell className="text-console-muted">{account.employeeCode}</Cell>
                                <Cell className="text-console-muted">{account.phoneNumber ?? '—'}</Cell>
                                <Cell>
                                    <StatusBadge status={account.invited ? 'invited' : account.status} />
                                </Cell>
                                <Cell className="text-console-muted">{account.createdAt ? shortDate(account.createdAt) : '—'}</Cell>
                                <td className="py-3 align-top">
                                    <AccountRowActions account={account} onEdit={setFormAccount} onAction={setPendingAction}>
                                        <Link
                                            href={route('super-admin.workforce.employees.show', account.id)}
                                            className="px-2 py-1 text-xs text-arka-teal transition-colors hover:bg-console-raised"
                                        >
                                            Clients &amp; rates
                                        </Link>
                                    </AccountRowActions>
                                </td>
                            </Row>
                        ))}
                    </Table>

                    <div className="mt-5">
                        <Pagination meta={admins.meta} />
                    </div>
                </Panel>
            </div>

            <Dialog
                open={formAccount !== null}
                onClose={() => setFormAccount(null)}
                side
                title={formAccount?.id ? 'Edit admin' : 'New admin'}
                description={formAccount?.id ? formAccount.employeeCode : 'Create an operations account'}
            >
                {formAccount !== null && (
                    <AccountForm
                        key={formAccount.id ?? 'new'}
                        account={formAccount.id ? formAccount : null}
                        storeUrl={route('super-admin.workforce.admins.store')}
                        updateUrl={formAccount.id ? route('super-admin.workforce.admins.update', formAccount.id) : null}
                        submitLabel="Send invite"
                        onDone={() => setFormAccount(null)}
                    />
                )}
            </Dialog>

            <AccountActionDialog
                action={pendingAction}
                routePrefix="super-admin.workforce.admins"
                roleLabel="Admin"
                onClose={() => setPendingAction(null)}
            />
        </SuperAdminLayout>
    );
}
