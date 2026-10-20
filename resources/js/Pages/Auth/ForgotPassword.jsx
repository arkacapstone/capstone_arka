import WaveBackdrop, { MailIcon } from '@/Components/Auth/WaveBackdrop';
import { ArrowLeftIcon } from '@/Components/Icons';
import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';

/**
 * Super Admin only: emails a new default password. Admins and contractors are reset by their Admin.
 */
export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    const inputClass = `peer block w-full rounded-full border border-slate-200 bg-arka-mist py-2.5 pl-4 pr-11 text-sm text-arka-navy placeholder:text-slate-400 transition duration-200 hover:border-arka-aqua hover:bg-white focus:border-arka-teal focus:bg-white focus:ring-2 focus:ring-arka-aqua/30 ${
        errors.email ? '!border-red-600 hover:!border-red-600 focus:!border-red-600 focus:!ring-red-600/30' : ''
    }`;

    return (
        <div className="relative flex min-h-screen items-center overflow-hidden bg-arka-mist px-4 py-10 sm:px-10 lg:px-20">
            <Head title="Forgot password" />

            <WaveBackdrop />

            <div className="relative z-10 mx-auto flex w-full max-w-6xl items-center justify-center lg:justify-start">
                <div className="w-full max-w-sm">
                    <div className="rounded-3xl bg-white p-8 shadow-xl shadow-arka-navy/10 ring-1 ring-slate-100 sm:p-10">
                        <Link href={route('login')} className="inline-block">
                            <img src="/images/login-logo.png" alt="ARKA" className="h-16 w-auto object-contain" />
                        </Link>

                        <h1 className="mt-4 text-2xl font-bold text-arka-navy">Lost your bearings?</h1>
                        <p className="mt-1 text-sm text-slate-500">
                            <span className="font-semibold text-arka-navy">Super Admin:</span> enter your email and ARKA will send you a new default password.
                        </p>

                        <p className="mt-3 rounded-xl bg-arka-mist px-4 py-3 text-xs leading-relaxed text-slate-500">
                            <span className="font-semibold text-arka-navy">Contractors and Admins:</span> ask your Admin to reset your password. You will get a new default
                            password by email.
                        </p>

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
                                        placeholder="captain@arka.co"
                                        autoComplete="username"
                                        autoFocus
                                        className={inputClass}
                                        onChange={(e) => setData('email', e.target.value)}
                                    />
                                    <MailIcon className="pointer-events-none absolute right-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400 transition-colors duration-200 peer-hover:text-arka-aqua peer-focus:text-arka-teal" />
                                </div>
                                <InputError message={errors.email} className="mt-2 pl-4" />
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="mt-1 w-full rounded-full bg-gradient-to-r from-arka-teal to-arka-aqua bg-[length:200%_100%] bg-left py-2.5 text-sm font-semibold text-white shadow-md shadow-arka-teal/30 transition-all duration-300 hover:bg-right hover:shadow-lg hover:shadow-arka-teal/40 focus:outline-none focus:ring-2 focus:ring-arka-aqua focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {processing ? 'Sending…' : 'Send default password'}
                            </button>
                        </form>

                        <Link
                            href={route('login')}
                            className="mt-6 inline-flex items-center gap-1.5 text-xs text-slate-500 underline-offset-4 transition-colors hover:text-arka-teal hover:underline"
                        >
                            <ArrowLeftIcon className="h-3.5 w-3.5" /> Back to log in
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
