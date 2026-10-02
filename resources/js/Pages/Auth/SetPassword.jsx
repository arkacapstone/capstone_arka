import ArkaLogo from '@/Components/ArkaLogo';
import { ConsoleButton, PasswordField } from '@/Components/Console/Field';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

/**
 * Forced first-login step: replace the temporary password before anything else loads.
 */
export default function SetPassword() {
    const { auth } = usePage().props;
    const { data, setData, put, processing, errors } = useForm({ password: '', password_confirmation: '' });

    const submit = (e) => {
        e.preventDefault();
        put(route('password.setup.update'));
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-console-bg px-4 py-10 font-barlow">
            <Head title="Set your password" />

            <div className="relative w-full max-w-md border border-console-line bg-console-panel p-8 sm:p-10">
                {['-left-[5px] -top-[9px]', '-right-[5px] -top-[9px]', '-bottom-[9px] -left-[5px]', '-bottom-[9px] -right-[5px]'].map((position) => (
                    <span key={position} aria-hidden="true" className={`absolute ${position} font-mono text-sm leading-none text-console-mark`}>
                        +
                    </span>
                ))}

                <ArkaLogo themed className="h-14 w-auto" />

                <p className="mt-6 text-[11px] font-medium uppercase tracking-[0.2em] text-console-muted">First login</p>
                <h1 className="mt-1 font-condensed text-3xl font-bold text-console-heading">Set your own password</h1>
                <p className="mt-2 text-sm leading-relaxed text-console-muted">
                    Welcome, {auth.user.name}. You signed in with your default password. Create your own password to continue to ARKA.
                </p>

                <form onSubmit={submit} className="mt-7 flex flex-col gap-5">
                    <PasswordField
                        id="password"
                        label="New password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        error={errors.password}
                        autoComplete="new-password"
                        autoFocus
                        required
                    />
                    <PasswordField
                        id="password_confirmation"
                        label="Confirm new password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        error={errors.password_confirmation}
                        autoComplete="new-password"
                        required
                    />
                    <ConsoleButton disabled={processing} className="w-full py-2.5">
                        Set password and continue
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
