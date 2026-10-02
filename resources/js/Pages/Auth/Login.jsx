import ArkaLogo from '@/Components/ArkaLogo';
import { EyeIcon, EyeOffIcon } from '@/Components/Icons';
import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

function MailIcon(props) {
    return (
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.6" {...props}>
            <rect x="2.5" y="4.5" width="15" height="11" rx="2" />
            <path d="m3 6 7 5 7-5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

function WaveBackdrop() {
    return (
        <div aria-hidden="true" className="pointer-events-none absolute inset-0 overflow-hidden">
            {/* Pale sun in the top right */}
            <div className="absolute -right-40 -top-56 h-[46rem] w-[46rem] rounded-full bg-arka-sky sm:-right-24" />

            {/* Layered waves: navy, teal, sand */}
            <svg
                className="absolute bottom-0 left-[-5%] h-[62%] w-[110%]"
                viewBox="0 0 1440 560"
                preserveAspectRatio="none"
            >
                <path
                    d="M0 330 C 220 150, 470 90, 720 150 C 930 200, 1130 320, 1440 250 L1440 560 L0 560 Z"
                    fill="#10203f"
                />
            </svg>
            <svg
                className="absolute bottom-0 left-[-5%] h-[50%] w-[110%]"
                viewBox="0 0 1440 450"
                preserveAspectRatio="none"
            >
                <path
                    d="M0 260 C 260 110, 520 70, 760 120 C 1000 170, 1200 260, 1440 190 L1440 450 L0 450 Z"
                    fill="#1a7a8c"
                />
            </svg>
            <svg
                className="absolute bottom-0 left-[-5%] h-[30%] w-[110%]"
                viewBox="0 0 1440 270"
                preserveAspectRatio="none"
            >
                <path
                    d="M0 230 C 200 170, 330 150, 520 150 C 820 150, 1100 60, 1440 90 L1440 270 L0 270 Z"
                    fill="#f3a620"
                />
            </svg>
        </div>
    );
}

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    const [showPassword, setShowPassword] = useState(false);
    const inputClass =
        'peer block w-full rounded-full border border-slate-200 bg-arka-mist py-2.5 pl-4 pr-11 text-sm text-arka-navy placeholder:text-slate-400 transition duration-200 hover:border-arka-aqua hover:bg-white focus:border-arka-teal focus:bg-white focus:ring-2 focus:ring-arka-aqua/30';

    // Errors outline the field in red.
    const fieldClass = (error) => `${inputClass} ${error ? '!border-red-600 hover:!border-red-600 focus:!border-red-600 focus:!ring-red-600/30' : ''}`;

    const iconClass =
        'pointer-events-none absolute right-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400 transition-colors duration-200 peer-hover:text-arka-aqua peer-focus:text-arka-teal';

    return (
        <div className="relative flex min-h-screen items-center overflow-hidden bg-arka-mist px-4 py-10 sm:px-10 lg:px-20">
            <Head title="Log in" />

            <WaveBackdrop />

            <div className="relative z-10 mx-auto flex w-full max-w-6xl items-center justify-center gap-16 lg:justify-between">
                <div className="w-full max-w-sm">
                    <div className="rounded-3xl bg-white p-8 shadow-xl shadow-arka-navy/10 ring-1 ring-slate-100 sm:p-10">
                        <Link href="/" className="inline-block">
                            <ArkaLogo className="h-16 w-auto" />
                        </Link>

                        <h1 className="mt-4 text-2xl font-bold text-arka-navy">Set sail</h1>
                        <p className="mt-1 text-sm text-slate-500">Log in to continue your voyage.</p>

                        {status && (
                            <div className="mt-4 rounded-xl bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700">
                                {status}
                            </div>
                        )}

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
                                        placeholder="captain@arka.co"
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
                                        className="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-full text-slate-400 transition-colors duration-200 hover:text-arka-teal peer-focus:text-arka-teal focus:outline-none focus-visible:text-arka-teal"
                                        aria-label={showPassword ? 'Hide password' : 'Show password'}
                                        title={showPassword ? 'Hide password' : 'Show password'}
                                    >
                                        {showPassword ? <EyeOffIcon className="h-5 w-5" /> : <EyeIcon className="h-5 w-5" />}
                                    </button>
                                </div>
                                <InputError message={errors.password} className="mt-2 pl-4" />
                            </div>

                            <label className="flex cursor-pointer items-center gap-2 pl-1 text-sm text-slate-500 transition-colors hover:text-arka-navy">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                    className="rounded border-slate-300 text-arka-teal focus:ring-arka-aqua"
                                />
                                Remember me
                            </label>

                            <button
                                type="submit"
                                disabled={processing}
                                className="mt-1 w-full rounded-full bg-gradient-to-r from-arka-teal to-arka-aqua bg-[length:200%_100%] bg-left py-2.5 text-sm font-semibold text-white shadow-md shadow-arka-teal/30 transition-all duration-300 hover:bg-right hover:shadow-lg hover:shadow-arka-teal/40 focus:outline-none focus:ring-2 focus:ring-arka-aqua focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {processing ? 'Logging in…' : 'Log in'}
                            </button>
                        </form>

                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className="mt-6 inline-block text-xs text-slate-500 underline-offset-4 transition-colors hover:text-arka-teal hover:underline"
                            >
                                Forgot your password?
                            </Link>
                        )}
                    </div>
                </div>

                <p className="hidden max-w-xs font-serif text-4xl leading-tight origin-bottom-left text-arka-navy motion-safe:animate-sway lg:block xl:text-5xl">
                    Steady hands, calm waters.
                </p>
            </div>
        </div>
    );
}
