import { appearanceOptions, saveAppearance } from '@/Components/Console/AppearanceSwitch';
import Dialog from '@/Components/Console/Dialog';
import { ConsoleButton, SavedNotice, SecondaryButton } from '@/Components/Console/Field';
import { MetricRow } from '@/Components/Console/Panel';
import { EyeIcon, EyeOffIcon, KeyIcon, LockIcon, PlusIcon } from '@/Components/Icons';
import ConfirmDialog from '@/Components/Workforce/ConfirmDialog';
import AppLayout from '@/Layouts/AppLayout';
import { birthdayRange, fullDate, timeAgo } from '@/lib/format';
import { PasswordConfirmationRequired, confirmPassword, deletePasskey, passkeyError, passkeysSupported, registerPasskey } from '@/lib/passkeys';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { forwardRef, useRef, useState } from 'react';

const sections = [
    { key: 'profile', label: 'Profile', route: 'profile.edit', title: 'Profile settings' },
    { key: 'security', label: 'Security', route: 'profile.security', title: 'Security settings' },
    { key: 'appearance', label: 'Appearance', route: 'profile.appearance.edit', title: 'Appearance settings' },
];

const inputClass =
    'block w-full rounded-none border border-console-line bg-console-panel px-3.5 py-2.5 text-sm text-console-text placeholder:text-console-dim transition-colors hover:border-arka-aqua focus:border-arka-teal focus:ring-1 focus:ring-arka-teal disabled:bg-console-raised';

function ageFrom(birthday) {
    if (!birthday) return null;

    const [year, month, day] = birthday.split('-').map(Number);
    const today = new Date();
    let age = today.getFullYear() - year;

    if (today.getMonth() + 1 < month || (today.getMonth() + 1 === month && today.getDate() < day)) age -= 1;

    return age >= 0 ? age : null;
}

function SectionHeading({ title, description }) {
    return (
        <div>
            <h2 className="font-condensed text-[22px] font-bold leading-tight text-console-heading">{title}</h2>
            {description && <p className="mt-1 text-sm text-console-muted">{description}</p>}
        </div>
    );
}

const Input = forwardRef(function Input({ id, label, error, hint, password = false, className = '', ...props }, ref) {
    const [visible, setVisible] = useState(false);

    return (
        <div className={className}>
            <label htmlFor={id} className="block font-condensed text-[15px] font-semibold text-console-heading">
                {label}
            </label>
            <div className="relative mt-2">
                <input
                    id={id}
                    ref={ref}
                    type={password ? (visible ? 'text' : 'password') : props.type}
                    className={`${inputClass} ${password ? 'pr-11' : ''} ${error ? '!border-console-error focus:!ring-console-error' : ''}`}
                    {...props}
                />
                {password && (
                    <button
                        type="button"
                        onClick={() => setVisible((value) => !value)}
                        className="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-console-muted transition-colors hover:text-arka-teal"
                        aria-label={visible ? 'Hide password' : 'Show password'}
                    >
                        {visible ? <EyeOffIcon className="h-[18px] w-[18px]" /> : <EyeIcon className="h-[18px] w-[18px]" />}
                    </button>
                )}
            </div>
            {hint && !error && <p className="mt-1.5 text-xs text-console-dim">{hint}</p>}
            {error && <p className="mt-1.5 text-xs text-console-error">{error}</p>}
        </div>
    );
});

/* ─── Profile ─────────────────────────────────────────────────────────── */

function ProfileSection({ account }) {
    const user = usePage().props.auth.user;
    const { data, setData, patch, errors, processing, recentlySuccessful, isDirty, reset } = useForm({
        name: user.name,
        birthday: account.birthday ?? '',
        phone_number: account.phoneNumber ?? '',
        address: account.address ?? '',
        emergency_contact_name: account.emergencyContactName ?? '',
        emergency_contact_number: account.emergencyContactNumber ?? '',
        ...(account.canEditEmail ? { email: user.email } : {}),
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.update'), { preserveScroll: true });
    };

    return (
        <div className="flex flex-col gap-12">
            <section>
                <SectionHeading title="Profile information" description="Keep your personal details and emergency contact up to date." />
                <form onSubmit={submit} className="mt-6 flex flex-col gap-5">
                    <Input id="name" label="Full name" value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} autoComplete="name" required />

                    <div className="grid gap-5 sm:grid-cols-[1fr_120px]">
                        <Input id="birthday" type="date" label="Birthday" value={data.birthday} onChange={(e) => setData('birthday', e.target.value)} error={errors.birthday} {...birthdayRange()} />
                        <Input id="age" label="Age" value={ageFrom(data.birthday) ?? '—'} disabled readOnly />
                    </div>

                    <Input id="phone_number" label="Phone number" value={data.phone_number} onChange={(e) => setData('phone_number', e.target.value)} error={errors.phone_number} placeholder="09XX XXX XXXX" />

                    <Input id="address" label="Address" value={data.address} onChange={(e) => setData('address', e.target.value)} error={errors.address} autoComplete="street-address" />

                    <div className="grid gap-5 sm:grid-cols-2">
                        <Input
                            id="emergency_contact_name"
                            label="Emergency contact name"
                            value={data.emergency_contact_name}
                            onChange={(e) => setData('emergency_contact_name', e.target.value)}
                            error={errors.emergency_contact_name}
                        />
                        <Input
                            id="emergency_contact_number"
                            label="Emergency contact number"
                            value={data.emergency_contact_number}
                            onChange={(e) => setData('emergency_contact_number', e.target.value)}
                            error={errors.emergency_contact_number}
                            placeholder="09XX XXX XXXX"
                        />
                    </div>

                    {account.canEditEmail ? (
                        <Input id="email" type="email" label="Email address" value={data.email} onChange={(e) => setData('email', e.target.value)} error={errors.email} autoComplete="username" required />
                    ) : (
                        <Input id="email" label="Email address" value={user.email} disabled readOnly hint="Your login email is set by your administrator." />
                    )}

                    <div className="flex items-center gap-4">
                        <ConsoleButton disabled={processing || !isDirty}>Save</ConsoleButton>
                        {isDirty && (
                            <button type="button" onClick={() => reset()} className="text-sm text-console-muted hover:text-arka-teal">
                                Cancel
                            </button>
                        )}
                        <SavedNotice show={recentlySuccessful} />
                    </div>
                </form>
            </section>

            <section>
                <SectionHeading title="Account" description="Managed by your organization." />
                <div className="mt-5 border-t border-console-line">
                    <MetricRow label="Contractor ID" value={account.employeeCode} />
                    <MetricRow label="Role" value={account.role} />
                    <MetricRow label="Status" value={account.status} />
                    <MetricRow label="Member since" value={fullDate(account.memberSince)} />
                </div>
                <p className="mt-4 text-sm text-console-muted">
                    Accounts aren't deleted from here. If you're leaving, your administrator deactivates the account and your records stay intact.
                </p>
            </section>
        </div>
    );
}

/* ─── Security ────────────────────────────────────────────────────────── */

function PasswordSection() {
    const passwordInput = useRef();
    const currentPasswordInput = useRef();
    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current.focus();
                }
            },
        });
    };

    return (
        <section>
            <SectionHeading title="Update password" description="Ensure your account is using a long, random password to stay secure." />
            <form onSubmit={submit} className="mt-6 flex flex-col gap-5">
                <Input
                    id="current_password"
                    ref={currentPasswordInput}
                    password
                    label="Current password"
                    placeholder="Current password"
                    value={data.current_password}
                    onChange={(e) => setData('current_password', e.target.value)}
                    error={errors.current_password}
                    autoComplete="current-password"
                />
                <Input
                    id="password"
                    ref={passwordInput}
                    password
                    label="New password"
                    placeholder="New password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                    autoComplete="new-password"
                />
                <Input
                    id="password_confirmation"
                    password
                    label="Confirm password"
                    placeholder="Confirm password"
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    error={errors.password_confirmation}
                    autoComplete="new-password"
                />
                <div className="flex items-center gap-4">
                    <ConsoleButton disabled={processing || !data.password}>Save</ConsoleButton>
                    <SavedNotice show={recentlySuccessful}>Password updated.</SavedNotice>
                </div>
            </form>
        </section>
    );
}

function PasskeysSection({ passkeys }) {
    const [adding, setAdding] = useState(false);
    const [name, setName] = useState('');
    const [needsPassword, setNeedsPassword] = useState(null); // the action to retry after confirming
    const [password, setPassword] = useState('');
    const [removing, setRemoving] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const supported = passkeysSupported();

    const run = async (action) => {
        setBusy(true);
        setError('');

        try {
            await action();
            router.reload({ only: ['passkeys'], preserveScroll: true });

            return true;
        } catch (failure) {
            if (failure instanceof PasswordConfirmationRequired) {
                setNeedsPassword(() => action);
            } else {
                setError(passkeyError(failure));
            }

            return false;
        } finally {
            setBusy(false);
        }
    };

    const add = async (e) => {
        e.preventDefault();

        if (await run(() => registerPasskey(name.trim() || 'My passkey'))) {
            setAdding(false);
            setName('');
        }
    };

    const confirmAndRetry = async (e) => {
        e.preventDefault();
        setBusy(true);
        setError('');

        try {
            await confirmPassword(password);
        } catch (failure) {
            setBusy(false);
            setError(failure.response?.data?.errors?.password?.[0] ?? 'That password is incorrect.');

            return;
        }

        const retry = needsPassword;
        setNeedsPassword(null);
        setPassword('');

        if (await run(retry)) {
            setAdding(false);
            setName('');
            setRemoving(null);
        }
    };

    return (
        <section>
            <SectionHeading title="Passkeys" description="Manage your passkeys for passwordless sign-in." />

            {passkeys.length === 0 ? (
                <div className="mt-6 flex flex-col items-center border border-console-line px-6 py-10 text-center">
                    <span className="flex h-14 w-14 items-center justify-center border border-console-line bg-console-raised text-console-heading">
                        <KeyIcon className="h-6 w-6" />
                    </span>
                    <p className="mt-4 font-condensed text-lg font-semibold text-console-heading">No passkeys yet</p>
                    <p className="mt-1 text-sm text-console-muted">Add a passkey to sign in without a password.</p>
                </div>
            ) : (
                <ul className="mt-6 border border-console-line">
                    {passkeys.map((passkey) => (
                        <li key={passkey.id} className="flex items-center gap-4 border-b border-console-line px-4 py-3 last:border-b-0">
                            <span className="flex h-9 w-9 shrink-0 items-center justify-center border border-arka-teal/40 bg-arka-teal/10 text-arka-teal">
                                <KeyIcon className="h-4 w-4" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium text-console-heading">{passkey.name}</p>
                                <p className="font-mono text-[11px] text-console-muted">
                                    Added {timeAgo(passkey.createdAt)} · {passkey.lastUsedAt ? `last used ${timeAgo(passkey.lastUsedAt)}` : 'not used yet'}
                                </p>
                            </div>
                            <button type="button" onClick={() => setRemoving(passkey)} className="px-2 py-1 text-xs text-console-muted hover:bg-console-raised hover:text-console-heading">
                                Remove
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {error && <p className="mt-3 text-sm text-console-error">{error}</p>}

            {!supported ? (
                <p className="mt-4 text-sm text-console-muted">This browser doesn't support passkeys.</p>
            ) : adding ? (
                <form onSubmit={add} className="mt-4 flex flex-wrap items-end gap-3">
                    <Input id="passkey_name" label="Passkey name" placeholder="e.g. Work laptop" value={name} onChange={(e) => setName(e.target.value)} className="min-w-[240px] flex-1" autoFocus />
                    <ConsoleButton type="submit" disabled={busy}>
                        Continue
                    </ConsoleButton>
                    <button type="button" onClick={() => setAdding(false)} className="py-2.5 text-sm text-console-muted hover:text-arka-teal">
                        Cancel
                    </button>
                </form>
            ) : (
                <SecondaryButton className="mt-4" onClick={() => setAdding(true)}>
                    <PlusIcon className="h-4 w-4" /> Add passkey
                </SecondaryButton>
            )}

            <Dialog open={needsPassword !== null} onClose={() => setNeedsPassword(null)} title="Confirm your password" description="For your security, confirm your password before changing passkeys.">
                <form onSubmit={confirmAndRetry} className="flex flex-col gap-4">
                    <Input id="confirm_password" password label="Password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="current-password" autoFocus />
                    {error && <p className="text-xs text-console-error">{error}</p>}
                    <div className="flex justify-end gap-2">
                        <SecondaryButton onClick={() => setNeedsPassword(null)}>Cancel</SecondaryButton>
                        <ConsoleButton type="submit" disabled={busy || !password}>
                            Confirm
                        </ConsoleButton>
                    </div>
                </form>
            </Dialog>

            <ConfirmDialog
                open={removing !== null && needsPassword === null}
                title="Remove this passkey?"
                body={removing ? `"${removing.name}" will no longer be able to sign you in. You can add it again later.` : ''}
                confirmLabel="Remove passkey"
                danger
                processing={busy}
                onConfirm={async () => {
                    const target = removing;

                    if (await run(() => deletePasskey(target.id))) setRemoving(null);
                }}
                onClose={() => setRemoving(null)}
            />
        </section>
    );
}

/* ─── Confirm password (before Security) ──────────────────────────────── */

function ConfirmSection() {
    const { data, setData, post, processing, errors, reset } = useForm({ password: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('profile.security.unlock'), { onFinish: () => reset('password') });
    };

    return (
        <section>
            <span className="flex h-12 w-12 items-center justify-center border border-arka-teal/40 bg-arka-teal/10 text-arka-teal">
                <LockIcon className="h-5 w-5" />
            </span>
            <div className="mt-5">
                <SectionHeading title="Confirm your password" description="For your security, confirm your password before changing your password or passkeys." />
            </div>
            <form onSubmit={submit} className="mt-6 flex flex-col gap-5">
                <Input
                    id="password"
                    password
                    label="Password"
                    placeholder="Current password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                    autoComplete="current-password"
                    autoFocus
                />
                <div className="flex items-center gap-4">
                    <ConsoleButton disabled={processing || !data.password}>Confirm</ConsoleButton>
                    <Link href={route('profile.edit')} className="text-sm text-console-muted hover:text-arka-teal">
                        Cancel
                    </Link>
                </div>
                <p className="text-xs text-console-dim">You'll be asked again each time you open Security.</p>
            </form>
        </section>
    );
}

/* ─── Appearance ──────────────────────────────────────────────────────── */

function AppearanceSection() {
    const current = usePage().props.auth.user.appearance;

    return (
        <section>
            <SectionHeading title="Appearance" description="Saved to your account, so it follows you to every device." />
            <div role="radiogroup" aria-label="Appearance" className="mt-6 grid gap-3 sm:grid-cols-3">
                {appearanceOptions.map(({ value, label, icon: Icon, hint }) => {
                    const active = current === value;

                    return (
                        <button
                            key={value}
                            type="button"
                            role="radio"
                            aria-checked={active}
                            onClick={() => !active && saveAppearance(value)}
                            className={`flex flex-col items-start gap-3 border px-4 py-4 text-left transition-colors ${
                                active ? 'border-arka-teal bg-arka-teal/10' : 'border-console-line hover:border-arka-teal hover:bg-console-raised'
                            }`}
                        >
                            <span className={`flex h-9 w-9 items-center justify-center border ${active ? 'border-arka-teal bg-arka-teal text-white' : 'border-console-line text-console-muted'}`}>
                                <Icon className="h-[18px] w-[18px]" />
                            </span>
                            <span>
                                <span className={`block font-condensed text-lg font-semibold leading-tight ${active ? 'text-arka-teal' : 'text-console-heading'}`}>{label}</span>
                                <span className="block text-xs text-console-muted">{hint}</span>
                            </span>
                        </button>
                    );
                })}
            </div>
        </section>
    );
}

/* ─── Page ────────────────────────────────────────────────────────────── */

export default function Profile({ section = 'profile', account, passkeys = [] }) {
    // The password check before Security belongs to the Security tab.
    const activeTab = section === 'confirm' ? 'security' : section;
    const current = sections.find((item) => item.key === activeTab) ?? sections[0];

    return (
        <AppLayout title={current.title} eyebrow="Settings">
            <div className="mx-auto max-w-[1560px]">
                <div>
                    <h2 className="font-condensed text-3xl font-bold text-console-heading">Settings</h2>
                    <p className="mt-1 text-console-muted">Manage your profile and account settings</p>
                </div>

                <div className="mt-8 flex flex-col gap-8 lg:flex-row lg:gap-12">
                    <nav aria-label="Settings" className="flex shrink-0 gap-1 overflow-x-auto lg:w-[220px] lg:flex-col">
                        {sections.map((item) => (
                            <Link
                                key={item.key}
                                href={route(item.route)}
                                preserveScroll
                                aria-current={item.key === activeTab ? 'page' : undefined}
                                className={`whitespace-nowrap border-l-2 px-3.5 py-2.5 font-condensed text-[16px] font-semibold transition-colors ${
                                    item.key === activeTab
                                        ? 'border-arka-teal bg-console-raised text-arka-teal'
                                        : 'border-transparent text-console-heading hover:bg-console-raised hover:text-arka-teal'
                                }`}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="min-w-0 max-w-2xl flex-1">
                        {section === 'profile' && <ProfileSection account={account} />}
                        {section === 'security' && (
                            <div className="flex flex-col gap-14">
                                <PasswordSection />
                                <PasskeysSection passkeys={passkeys} />
                            </div>
                        )}
                        {section === 'appearance' && <AppearanceSection />}
                        {section === 'confirm' && <ConfirmSection />}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
