import ArkaLogo from '@/Components/ArkaLogo';
import Field, { ConsoleButton } from '@/Components/Console/Field';
import { birthdayRange } from '@/lib/format';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

function ageFrom(birthday) {
    if (!birthday) return null;

    const [year, month, day] = birthday.split('-').map(Number);
    const today = new Date();
    let age = today.getFullYear() - year;

    if (today.getMonth() + 1 < month || (today.getMonth() + 1 === month && today.getDate() < day)) age -= 1;

    return age >= 0 ? age : null;
}

/**
 * Forced first-login step after the password change: the new account owner fills in
 * their own personal details so the Admin doesn't have to.
 */
export default function CompleteProfile({ account }) {
    const { auth } = usePage().props;
    const { data, setData, put, processing, errors } = useForm({
        phone_number: account.phoneNumber ?? '',
        birthday: account.birthday ?? '',
        address: '',
        emergency_contact_name: '',
        emergency_contact_number: '',
    });

    const age = ageFrom(data.birthday);

    const submit = (e) => {
        e.preventDefault();
        put(route('profile.setup.update'));
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-console-bg px-4 py-10 font-barlow">
            <Head title="Complete your profile" />

            <div className="relative w-full max-w-xl border border-console-line bg-console-panel p-8 sm:p-10">
                {['-left-[5px] -top-[9px]', '-right-[5px] -top-[9px]', '-bottom-[9px] -left-[5px]', '-bottom-[9px] -right-[5px]'].map((position) => (
                    <span key={position} aria-hidden="true" className={`absolute ${position} font-mono text-sm leading-none text-console-mark`}>
                        +
                    </span>
                ))}

                <ArkaLogo themed className="h-14 w-auto" />

                <p className="mt-6 text-[11px] font-medium uppercase tracking-[0.2em] text-console-muted">
                    Last step · {account.role} {account.employeeCode}
                </p>
                <h1 className="mt-1 font-condensed text-3xl font-bold text-console-heading">Complete your profile</h1>
                <p className="mt-2 text-sm leading-relaxed text-console-muted">
                    Welcome, {auth.user.name}. Fill in your personal details. You can update them later in Profile.
                </p>

                <form onSubmit={submit} className="mt-7 flex flex-col gap-5">
                    <Field
                        id="phone_number"
                        label="Phone number"
                        value={data.phone_number}
                        onChange={(e) => setData('phone_number', e.target.value)}
                        error={errors.phone_number}
                        placeholder="09XX XXX XXXX"
                        autoComplete="tel"
                        autoFocus
                        required
                    />

                    <div className="grid gap-5 sm:grid-cols-[1fr_110px]">
                        <Field
                            id="birthday"
                            type="date"
                            label="Birthday"
                            value={data.birthday}
                            onChange={(e) => setData('birthday', e.target.value)}
                            error={errors.birthday}
                            {...birthdayRange()}
                            autoComplete="bday"
                            required
                        />
                        <Field id="age" label="Age" value={age ?? '—'} disabled readOnly />
                    </div>

                    <Field
                        id="address"
                        label="Address"
                        value={data.address}
                        onChange={(e) => setData('address', e.target.value)}
                        error={errors.address}
                        placeholder="House no., street, barangay, city, province"
                        autoComplete="street-address"
                        required
                    />

                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field
                            id="emergency_contact_name"
                            label="Emergency contact name"
                            value={data.emergency_contact_name}
                            onChange={(e) => setData('emergency_contact_name', e.target.value)}
                            error={errors.emergency_contact_name}
                            required
                        />
                        <Field
                            id="emergency_contact_number"
                            label="Emergency contact number"
                            value={data.emergency_contact_number}
                            onChange={(e) => setData('emergency_contact_number', e.target.value)}
                            error={errors.emergency_contact_number}
                            placeholder="09XX XXX XXXX"
                            required
                        />
                    </div>

                    <ConsoleButton disabled={processing} className="w-full py-2.5">
                        Save and continue
                    </ConsoleButton>
                </form>

                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="mt-6 text-sm text-console-muted transition-colors hover:text-arka-teal"
                >
                    Sign out instead
                </Link>
            </div>
        </div>
    );
}
