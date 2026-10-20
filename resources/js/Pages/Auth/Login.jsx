import WaveBackdrop, { MailIcon } from '@/Components/Auth/WaveBackdrop';
import { ArrowLeftIcon, EyeIcon, EyeOffIcon } from '@/Components/Icons';
import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const inputClass =
    'peer block w-full rounded-full border border-slate-200 bg-arka-mist py-2.5 pl-4 pr-11 text-sm text-arka-navy placeholder:text-slate-400 transition duration-200 hover:border-arka-aqua hover:bg-white focus:border-arka-teal focus:bg-white focus:ring-2 focus:ring-arka-aqua/30';

// Errors outline the field in red.
const fieldClass = (error) => `${inputClass} ${error ? '!border-red-600 hover:!border-red-600 focus:!border-red-600 focus:!ring-red-600/30' : ''}`;

const iconClass =
    'pointer-events-none absolute right-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400 transition-colors duration-200 peer-hover:text-arka-aqua peer-focus:text-arka-teal';

const primaryButton =
    'w-full rounded-full bg-gradient-to-r from-arka-teal to-arka-aqua bg-[length:200%_100%] bg-left py-2.5 text-sm font-semibold text-white shadow-md shadow-arka-teal/30 transition-all duration-300 hover:bg-right hover:shadow-lg hover:shadow-arka-teal/40 focus:outline-none focus:ring-2 focus:ring-arka-aqua focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60';

const backButton =
    'flex w-full items-center justify-center gap-2 rounded-full bg-arka-navy py-2.5 text-sm font-semibold text-white transition-colors hover:bg-arka-navy/90 focus:outline-none focus:ring-2 focus:ring-arka-aqua focus:ring-offset-2';

// The sand of the bottom wave (WaveBackdrop), used for the line under the header.
const SAND = '#f3a620';

// Log in is solid navy (the brand blue) with sand text; Register is outlined in the same sand as the line.
const loginButton =
    'rounded-full bg-arka-navy text-sm font-semibold text-[#f3a620] shadow-md shadow-arka-navy/30 transition-colors duration-200 hover:bg-arka-navy/90 focus:outline-none focus:ring-2 focus:ring-arka-aqua focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60';

const registerButton =
    'rounded-full border-2 border-[#f3a620] bg-white/70 text-sm font-semibold text-arka-navy transition-colors duration-200 hover:bg-[#f3a620]/10 focus:outline-none focus:ring-2 focus:ring-[#f3a620] focus:ring-offset-2';

const titles = {
    login: 'Log in',
    register: 'Register your Home Office',
};

/** A labelled field of the register card. */
function RegisterField({ id, label, error, ...props }) {
    return (
        <div>
            <label htmlFor={id} className="mb-1 block pl-4 text-xs text-slate-500">
                {label}
            </label>
            <input id={id} name={id} className={fieldClass(error)} {...props} />
            <InputError message={error} className="mt-2 pl-4" />
        </div>
    );
}

/**
 * Register your home office: a new workforce company makes its home office and its Super Admin
 * account, then lands on its own dashboard.
 */
function RegisterForm({ onBack }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        home_office_name: '',
        home_office_address: '',
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('register'), {
            preserveState: true,
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const field = (id, label, props = {}) => (
        <RegisterField id={id} label={label} value={data[id]} error={errors[id]} onChange={(e) => setData(id, e.target.value)} {...props} />
    );

    return (
        <form onSubmit={submit} className="mt-4 flex flex-col gap-3">
            {field('home_office_name', 'Home office name', { autoComplete: 'organization', autoFocus: true })}
            {field('home_office_address', 'Address (optional)', { autoComplete: 'street-address' })}
            {field('name', 'Full name', { autoComplete: 'name' })}
            {field('email', 'Email', { type: 'email', autoComplete: 'username' })}
            {field('password', 'Password', { type: 'password', autoComplete: 'new-password' })}
            {field('password_confirmation', 'Confirm password', { type: 'password', autoComplete: 'new-password' })}

            <button type="submit" disabled={processing} className={`${primaryButton} mt-2`}>
                {processing ? 'Registering…' : 'Register home office'}
            </button>
            <button type="button" onClick={onBack} className={backButton}>
                <ArrowLeftIcon className="h-4 w-4" />
                Back to log in
            </button>
        </form>
    );
}

/**
 * Landing page with the login card: every role (Super Admin, Admin, Contractor) signs in with
 * the same form. "Register home office" (the header button, or the button in the card) switches
 * the card to the register form; /register opens it directly.
 */
export default function Login({ status, canResetPassword, startWith = 'login' }) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const [open, setOpen] = useState(true);
    const [view, setView] = useState(startWith);
    const [showPassword, setShowPassword] = useState(false);

    // Opens the card on the login or the register form.
    const show = (next) => {
        clearErrors();
        reset('password');
        setShowPassword(false);
        setView(next);
        setOpen(true);
    };

    // A login error reopens the login card so it can be seen.
    useEffect(() => {
        if (errors.email || errors.password) {
            setView('login');
            setOpen(true);
        }
    }, [errors]);

    // Esc closes the card.
    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const onKey = (event) => event.key === 'Escape' && setOpen(false);
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [open]);

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            preserveState: true,
            onFinish: () => reset('password'),
        });
    };

    return (
        <div className="relative flex min-h-screen flex-col overflow-hidden bg-arka-mist">
            <Head title={view === 'register' ? 'Register your home office' : 'Log in'} />

            <WaveBackdrop low />

            {/* Header, with a sand line (the bottom wave's color) across the full width under it. */}
            <header className="relative z-10 flex items-center justify-between border-b-2 px-4 py-5 sm:px-10 lg:px-20" style={{ borderColor: SAND }}>
                <Link href="/" className="flex items-center gap-3">
                    <img src="/images/arka-mark.png" alt="" className="h-10 w-10 object-contain" />
                    <span className="font-condensed text-2xl font-bold tracking-[0.2em] text-arka-navy">ARKA</span>
                </Link>

                <div className="flex items-center gap-2 sm:gap-3">
                    <button
                        type="button"
                        onClick={() => show('register')}
                        className={`${registerButton} px-4 py-2 sm:px-6`}
                    >
                        Register home office
                    </button>
                    <button
                        type="button"
                        onClick={() => show('login')}
                        className={`${loginButton} px-6 py-2`}
                    >
                        Log in
                    </button>
                </div>
            </header>

            <main className="relative z-10 flex flex-1 items-center px-4 pb-16 sm:px-10 lg:px-20">
                <div className="max-w-xl">
                    <img src="/images/login-logo.png" alt="ARKA" className="h-24 w-auto object-contain sm:h-28" />
                    <h1 className="mt-6 text-3xl font-bold leading-tight text-arka-navy sm:text-4xl">
                        Workforce attendance and payroll, in one place.
                    </h1>
                    <p className="mt-4 max-w-md text-base text-slate-600">
                        Track time, verify attendance and release payslips for your contractors and clients.
                    </p>
                    {/* Under the text on small screens; on wide screens it sits on the right instead. */}
                    <p className="mt-8 origin-bottom-left font-serif text-3xl leading-tight text-arka-navy motion-safe:animate-sway lg:hidden">
                        Steady hands, calm waters.
                    </p>
                </div>
            </main>

            <p className="absolute right-20 top-[48%] z-10 hidden origin-bottom-left font-serif text-3xl leading-tight text-arka-navy motion-safe:animate-sway lg:block xl:right-32">
                Steady hands, calm waters.
            </p>

            {open && (
                // The card sits near the top, just below the header logo, and slides down into place.
                <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-arka-navy/40 px-4 pb-10 pt-20 backdrop-blur-sm" onMouseDown={(e) => e.target === e.currentTarget && setOpen(false)}>
                    <div key={view} role="dialog" aria-modal="true" aria-labelledby="login-title" className="w-full max-w-sm rounded-3xl bg-white p-8 shadow-xl shadow-arka-navy/20 ring-1 ring-slate-100 motion-safe:animate-drop-in sm:p-10">
                        <h2 id="login-title" className="text-xl font-bold text-arka-navy">
                            {titles[view]}
                        </h2>

                        {view === 'register' && (
                            <>
                                <p className="mt-1 text-sm text-slate-500">Your home office and its Super Admin account.</p>
                                <RegisterForm onBack={() => show('login')} />
                            </>
                        )}

                        {view === 'login' && (
                            <>
                                {status && <div className="mt-4 rounded-xl bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700">{status}</div>}

                                <form onSubmit={submit} className="mt-6 flex flex-col gap-4">
                                    <div>
                                        <label htmlFor="email" className="sr-only">
                                            Email
                                        </label>
                                        <div className="relative">
                                            <input
                                                id="email"
                                                type="email"
                                                name="email"
                                                value={data.email}
                                                placeholder="Email"
                                                autoComplete="username"
                                                autoFocus
                                                className={fieldClass(errors.email)}
                                                onChange={(e) => setData('email', e.target.value)}
                                            />
                                            <MailIcon className={iconClass} />
                                        </div>
                                        <InputError message={errors.email} className="mt-2 pl-4" />
                                    </div>

                                    <div>
                                        <label htmlFor="password" className="sr-only">
                                            Password
                                        </label>
                                        <div className="relative">
                                            <input
                                                id="password"
                                                type={showPassword ? 'text' : 'password'}
                                                name="password"
                                                value={data.password}
                                                placeholder="Password"
                                                autoComplete="current-password"
                                                className={fieldClass(errors.password)}
                                                onChange={(e) => setData('password', e.target.value)}
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setShowPassword((value) => !value)}
                                                className="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-full text-slate-400 transition-colors duration-200 hover:text-arka-teal focus:outline-none focus-visible:text-arka-teal"
                                                aria-label={showPassword ? 'Hide password' : 'Show password'}
                                                title={showPassword ? 'Hide password' : 'Show password'}
                                            >
                                                {showPassword ? <EyeOffIcon className="h-5 w-5" /> : <EyeIcon className="h-5 w-5" />}
                                            </button>
                                        </div>
                                        <InputError message={errors.password} className="mt-2 pl-4" />
                                    </div>

                                    <div className="flex items-center justify-between gap-3 pl-1">
                                        <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-500 transition-colors hover:text-arka-navy">
                                            <input
                                                type="checkbox"
                                                name="remember"
                                                checked={data.remember}
                                                onChange={(e) => setData('remember', e.target.checked)}
                                                className="rounded border-slate-300 text-arka-teal focus:ring-arka-aqua"
                                            />
                                            Remember me
                                        </label>
                                        {canResetPassword && (
                                            <Link href={route('password.request')} className="text-sm text-arka-teal underline-offset-4 hover:underline">
                                                Forgot password?
                                            </Link>
                                        )}
                                    </div>

                                    <button type="submit" disabled={processing} className={`${loginButton} mt-1 w-full py-2.5`}>
                                        {processing ? 'Logging in…' : 'Log in'}
                                    </button>

                                    <button type="button" onClick={() => show('register')} className={`${registerButton} w-full py-2.5`}>
                                        Register your home office
                                    </button>
                                </form>
                            </>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
