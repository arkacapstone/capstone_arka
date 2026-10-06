import Dialog from '@/Components/Console/Dialog';
import Field, { ConsoleButton, SelectField } from '@/Components/Console/Field';
import Panel, { PanelHeading } from '@/Components/Console/Panel';
import { Tag } from '@/Components/Console/StatusBadge';
import ConfirmDialog from '@/Components/Workforce/ConfirmDialog';
import StatTile from '@/Components/Workforce/StatTile';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import useFilters, { SearchInput } from '@/Components/Workforce/useFilters';
import WorkforceTabs from '@/Components/Workforce/WorkforceTabs';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { peso, shortDate, todayIso } from '@/lib/format';
import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const statusTone = { available: 'closed', assigned: 'live', lost: 'waiting' };
const statusLabel = { available: 'Available', assigned: 'Assigned', lost: 'Lost' };

function DeviceForm({ device, types, contractors, onDone }) {
    const { data, setData, post, put, processing, errors } = useForm({
        device_type: device?.type ?? types[0],
        device_name: device?.name ?? '',
        serial_number: device?.serial ?? '',
        value: device?.value ?? '',
        // A new device can go straight to a contractor; an existing one is assigned from its row.
        ...(device ? {} : { employee_id: '', assigned_date: todayIso() }),
    });

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onDone };
        device ? put(route('super-admin.workforce.devices.update', device.id), options) : post(route('super-admin.workforce.devices.store'), options);
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-5">
            <SelectField
                id="device_type"
                label="Type"
                value={data.device_type}
                onChange={(e) => setData('device_type', e.target.value)}
                error={errors.device_type}
                options={types.map((type) => ({ value: type, label: type }))}
            />
            <Field
                id="device_name"
                label="Brand / model"
                value={data.device_name}
                onChange={(e) => setData('device_name', e.target.value)}
                error={errors.device_name}
                placeholder={data.device_type === 'Other' ? 'What is it? e.g. Docking station' : 'e.g. Logitech K120'}
                required
            />
            <Field id="serial_number" label="Serial number (optional)" value={data.serial_number} onChange={(e) => setData('serial_number', e.target.value)} error={errors.serial_number} />
            <div>
                <Field id="value" type="number" step="0.01" min="0.01" label="Value (₱)" value={data.value} onChange={(e) => setData('value', e.target.value)} error={errors.value} required />
                <p className="mt-1.5 text-xs text-console-muted">If this device is lost, this amount is deducted from the contractor's next payroll.</p>
            </div>
            {!device && (
                <div className="flex flex-col gap-4 border-t border-console-line pt-5">
                    <SelectField
                        id="employee_id"
                        label="Assign to (optional)"
                        value={data.employee_id}
                        onChange={(e) => setData('employee_id', e.target.value)}
                        error={errors.employee_id}
                        placeholder="Not yet — keep it available"
                        options={contractors}
                    />
                    {data.employee_id !== '' && (
                        <Field
                            id="assigned_date"
                            type="date"
                            label="Given on"
                            value={data.assigned_date}
                            onChange={(e) => setData('assigned_date', e.target.value)}
                            error={errors.assigned_date}
                            required
                        />
                    )}
                </div>
            )}
            <ConsoleButton disabled={processing}>{device ? 'Save device' : 'Add device'}</ConsoleButton>
        </form>
    );
}

function AssignForm({ device, contractors, onDone }) {
    const { data, setData, post, processing, errors } = useForm({ employee_id: contractors[0]?.value ?? '', assigned_date: todayIso() });

    const submit = (e) => {
        e.preventDefault();
        post(route('super-admin.workforce.devices.assign', device.id), { preserveScroll: true, onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-5">
            <SelectField id="employee_id" label="Contractor" value={data.employee_id} onChange={(e) => setData('employee_id', e.target.value)} error={errors.employee_id} options={contractors} />
            <Field id="assigned_date" type="date" label="Given on" value={data.assigned_date} onChange={(e) => setData('assigned_date', e.target.value)} error={errors.assigned_date} required />
            <ConsoleButton disabled={processing}>Assign device</ConsoleButton>
        </form>
    );
}

export default function Devices({ devices, filters, counts, contractors, types }) {
    const { search, setSearch, apply } = useFilters('super-admin.workforce.devices.index', filters);
    const [editing, setEditing] = useState(null); // null = closed, {} = new, device = edit
    const [assigning, setAssigning] = useState(null);
    const [confirming, setConfirming] = useState(null); // { device, action: 'returned' | 'lost' }
    const [processing, setProcessing] = useState(false);
    const { errors } = usePage().props;

    const confirm = () =>
        router.post(
            route(`super-admin.workforce.devices.${confirming.action}`, confirming.device.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setConfirming(null);
                },
            },
        );

    return (
        <SuperAdminLayout title="Workforce Management">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <WorkforceTabs />

                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <StatTile label="All devices" value={counts.total} active={!filters.status} onClick={() => apply({ status: '' })} />
                    <StatTile label="Available" value={counts.available} active={filters.status === 'available'} onClick={() => apply({ status: 'available' })} />
                    <StatTile label="Assigned" value={counts.assigned} active={filters.status === 'assigned'} onClick={() => apply({ status: 'assigned' })} />
                    <StatTile label="Lost" value={counts.lost} active={filters.status === 'lost'} onClick={() => apply({ status: 'lost' })} />
                </div>

                <Panel>
                    <PanelHeading
                        title="Devices"
                        subtitle="Company equipment lent to contractors. A lost device is deducted at its own value from the contractor's next payroll."
                        action={<ConsoleButton onClick={() => setEditing({})}>Add device</ConsoleButton>}
                    />

                    {(errors.device || errors.value || errors.employee_id) && (
                        <p className="mt-4 text-sm text-console-error">{errors.device ?? errors.value ?? errors.employee_id}</p>
                    )}

                    <div className="mb-5 mt-6">
                        <SearchInput value={search} onChange={setSearch} placeholder="Search device, type or serial…" label="Search devices" />
                    </div>

                    <Table
                        columns={['Device', 'Value', 'With', 'Status', 'Actions']}
                        isEmpty={devices.length === 0}
                        emptyMessage={filters.search || filters.status ? 'No devices match these filters.' : 'No devices yet. Add the equipment you lend to contractors.'}
                    >
                        {devices.map((device) => (
                            <Row key={device.id}>
                                <Cell>
                                    <p className="font-medium text-console-heading">{device.type ?? 'Device'}</p>
                                    <p className="text-xs text-console-text">{device.name}</p>
                                    {device.serial && <p className="font-mono text-[11px] text-console-dim">SN {device.serial}</p>}
                                </Cell>
                                <Cell className="font-mono">{peso(device.value)}</Cell>
                                <Cell>
                                    {device.holder ? (
                                        <>
                                            <p className="text-console-text">{device.holder.name}</p>
                                            <p className="font-mono text-[11px] text-console-dim">since {shortDate(device.holder.since)}</p>
                                        </>
                                    ) : device.lostBy ? (
                                        <>
                                            <p className="text-console-text">{device.lostBy}</p>
                                            <p className="font-mono text-[11px] text-console-dim">{peso(device.deducted)} deducted</p>
                                        </>
                                    ) : (
                                        <span className="text-console-dim">—</span>
                                    )}
                                </Cell>
                                <Cell>
                                    <Tag tone={statusTone[device.status] ?? 'closed'}>{statusLabel[device.status] ?? device.status}</Tag>
                                </Cell>
                                <td className="py-3 text-right align-top">
                                    <div className="flex justify-end gap-1">
                                        {device.status === 'available' && (
                                            <button type="button" onClick={() => setAssigning(device)} className="px-2 py-1 text-xs text-arka-teal hover:bg-console-raised">
                                                Assign
                                            </button>
                                        )}
                                        {device.status === 'assigned' && (
                                            <>
                                                <button
                                                    type="button"
                                                    onClick={() => setConfirming({ device, action: 'returned' })}
                                                    className="px-2 py-1 text-xs text-arka-teal hover:bg-console-raised"
                                                >
                                                    Returned
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setConfirming({ device, action: 'lost' })}
                                                    className="px-2 py-1 text-xs text-console-heading hover:bg-console-raised"
                                                >
                                                    Mark lost
                                                </button>
                                            </>
                                        )}
                                        {device.status !== 'lost' && (
                                            <button type="button" onClick={() => setEditing(device)} className="px-2 py-1 text-xs text-console-muted hover:bg-console-raised">
                                                Edit
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </Row>
                        ))}
                    </Table>
                </Panel>
            </div>

            <Dialog open={editing !== null} onClose={() => setEditing(null)} side title={editing?.id ? 'Edit device' : 'Add device'}>
                {editing !== null && <DeviceForm key={editing.id ?? 'new'} device={editing.id ? editing : null} types={types} contractors={contractors} onDone={() => setEditing(null)} />}
            </Dialog>

            <Dialog open={assigning !== null} onClose={() => setAssigning(null)} side title="Assign device" description={assigning?.name}>
                {assigning && <AssignForm key={assigning.id} device={assigning} contractors={contractors} onDone={() => setAssigning(null)} />}
            </Dialog>

            <ConfirmDialog
                open={confirming !== null}
                title={confirming?.action === 'lost' ? 'Mark this device as lost?' : 'Mark this device as returned?'}
                body={
                    confirming?.action === 'lost'
                        ? `${confirming.device.holder?.name} will be charged ${peso(confirming.device.value)}, this device's value, on their next payroll. You can also hold their payroll in Payroll Management until it is resolved.`
                        : `${confirming?.device.holder?.name ?? 'The contractor'} gave the device back. It becomes available again.`
                }
                confirmLabel={confirming?.action === 'lost' ? 'Mark lost' : 'Mark returned'}
                danger={confirming?.action === 'lost'}
                processing={processing}
                onConfirm={confirm}
                onClose={() => !processing && setConfirming(null)}
            />
        </SuperAdminLayout>
    );
}
