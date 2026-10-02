import Dialog from '@/Components/Console/Dialog';
import Field, { ConsoleButton, PasswordField, SecondaryButton } from '@/Components/Console/Field';
import Panel, { PanelHeading } from '@/Components/Console/Panel';
import RuleGroupForm from '@/Components/Console/RuleGroupForm';
import { Tag } from '@/Components/Console/StatusBadge';
import { PlusIcon } from '@/Components/Icons';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

function LeaveTypeDialog({ leaveType, onClose }) {
    const editing = leaveType?.id !== undefined;
    const { data, setData, post, put, processing, errors } = useForm({
        name: leaveType?.name ?? '',
        paid: leaveType?.paid ?? true,
    });

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        editing ? put(route('super-admin.rules.leave-types.update', leaveType.id), options) : post(route('super-admin.rules.leave-types.store'), options);
    };

    return (
        <Dialog open={leaveType !== null} onClose={onClose} side title={editing ? 'Edit leave type' : 'Add leave type'}>
            <form onSubmit={submit} className="flex flex-col gap-5">
                <Field id="name" label="Name" value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} required />
                <label htmlFor="paid" className="flex cursor-pointer items-start gap-3 border border-console-line px-4 py-3 hover:border-arka-teal">
                    <input
                        id="paid"
                        type="checkbox"
                        checked={data.paid}
                        onChange={(e) => setData('paid', e.target.checked)}
                        className="mt-0.5 rounded-none border-console-line text-arka-teal focus:ring-arka-teal"
                    />
                    <span>
                        <span className="block text-sm font-medium text-console-heading">Paid leave</span>
                        <span className="mt-0.5 block text-xs text-console-muted">Paid leave is never deducted from payroll. Unpaid leave is deducted at the daily rate.</span>
                    </span>
                </label>
                <div className="flex justify-end gap-2">
                    <SecondaryButton onClick={onClose}>Cancel</SecondaryButton>
                    <ConsoleButton type="submit" disabled={processing}>
                        {editing ? 'Save leave type' : 'Add leave type'}
                    </ConsoleButton>
                </div>
            </form>
        </Dialog>
    );
}

/**
 * The Gmail account ARKA sends invite links and password resets from.
 * The saved App Password is never shown; leave it blank to keep it.
 */
function MailSenderPanel({ mail }) {
    const [testing, setTesting] = useState(false);
    const { data, setData, put, processing, errors, isDirty } = useForm({
        username: mail.username,
        password: '',
        from_name: mail.fromName,
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('super-admin.rules.mail.update'), { preserveScroll: true, onSuccess: () => setData('password', '') });
    };

    const sendTest = () =>
        router.post(route('super-admin.rules.mail.test'), {}, { preserveScroll: true, onStart: () => setTesting(true), onFinish: () => setTesting(false) });

    return (
        <Panel>
            <PanelHeading
                title="Email sender"
                subtitle="The Gmail account ARKA sends invite links and password resets from."
                action={<Tag tone={mail.configured ? 'live' : 'waiting'}>{mail.configured ? 'Set up' : 'Not set up'}</Tag>}
            />
            <form onSubmit={submit} className="mt-6 grid gap-5 lg:grid-cols-3">
                <Field
                    id="mail_username"
                    type="email"
                    label="Username (Gmail)"
                    value={data.username}
                    onChange={(e) => setData('username', e.target.value)}
                    error={errors.username}
                    placeholder="name@gmail.com"
                    autoComplete="off"
                    required
                />
                <div>
                    <PasswordField
                        id="mail_password"
                        label="Password (App Password)"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        error={errors.password}
                        placeholder={mail.hasPassword ? 'Saved. Leave blank to keep it' : 'xxxx xxxx xxxx xxxx'}
                        autoComplete="new-password"
                        required={!mail.hasPassword}
                    />
                    <p className="mt-1.5 text-xs text-console-muted">
                        Not the normal Gmail password. Create one at{' '}
                        <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener noreferrer" className="font-medium text-arka-teal underline hover:no-underline">
                            myaccount.google.com/apppasswords
                        </a>{' '}
                        (
                        <a href="https://myaccount.google.com/signinoptions/twosv" target="_blank" rel="noopener noreferrer" className="text-arka-teal underline hover:no-underline">
                            2-Step Verification
                        </a>{' '}
                        must be on).
                    </p>
                </div>
                <Field
                    id="mail_from_name"
                    label="Sender name"
                    value={data.from_name}
                    onChange={(e) => setData('from_name', e.target.value)}
                    error={errors.from_name}
                    required
                />
                <div className="flex flex-wrap items-center gap-3 lg:col-span-3">
                    <ConsoleButton type="submit" disabled={processing || (!isDirty && mail.configured)}>
                        Save email sender
                    </ConsoleButton>
                    <SecondaryButton onClick={sendTest} disabled={testing || !mail.configured || isDirty}>
                        {testing ? 'Sending…' : 'Send test email'}
                    </SecondaryButton>
                    <p className="text-xs text-console-muted">The test email goes to the sender's own inbox.</p>
                </div>
            </form>
        </Panel>
    );
}

export default function Index({ groups, leaveTypes, mail }) {
    const [editing, setEditing] = useState(null); // null | {} (new) | leave type

    return (
        <SuperAdminLayout title="System & Rules">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <p className="text-console-muted">
                    System &amp; Rules defines the rules; Payroll applies them. Changes affect payroll computed from now on — released payslips never change.
                    Tithes and devotional penalties are not payroll deductions, so they are not configured here.
                </p>

                {groups.map((group) => (
                    <RuleGroupForm key={group.key} title={group.title} subtitle={group.subtitle} rules={group.rules} />
                ))}

                <MailSenderPanel mail={mail} />

                <Panel>
                    <PanelHeading
                        title="Leave types"
                        subtitle="Contractors choose paid or unpaid leave; ARKA files it under the first matching type below."
                        action={
                            <ConsoleButton onClick={() => setEditing({})}>
                                <PlusIcon className="h-4 w-4" /> Add leave type
                            </ConsoleButton>
                        }
                    />
                    <div className="mt-6">
                        <Table columns={['Name', 'Pay', 'Requests', 'Action']} isEmpty={leaveTypes.length === 0} emptyMessage="No leave types yet. ARKA creates “Paid leave” and “Unpaid leave” on first use." minWidth={640}>
                            {leaveTypes.map((type) => (
                                <Row key={type.id}>
                                    <Cell className="font-medium text-console-heading">{type.name}</Cell>
                                    <Cell>
                                        <Tag tone={type.paid ? 'live' : 'closed'}>{type.paid ? 'Paid' : 'Unpaid'}</Tag>
                                    </Cell>
                                    <Cell className="font-mono">{type.requests}</Cell>
                                    <td className="py-3 text-right align-top">
                                        <button type="button" onClick={() => setEditing(type)} className="px-2 py-1 text-xs text-arka-teal hover:bg-console-raised">
                                            Edit
                                        </button>
                                    </td>
                                </Row>
                            ))}
                        </Table>
                    </div>
                </Panel>
            </div>

            <LeaveTypeDialog key={editing?.id ?? (editing ? 'new' : 'closed')} leaveType={editing} onClose={() => setEditing(null)} />
        </SuperAdminLayout>
    );
}
