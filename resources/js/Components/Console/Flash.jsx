import { SecondaryButton } from '@/Components/Console/Field';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/** Confirmation toast for `flash.success`. Fades out after a few seconds. */
export function FlashToast() {
    const { flash } = usePage().props;
    const [visible, setVisible] = useState(false);

    const message = flash?.warning ?? flash?.success;

    useEffect(() => {
        if (!message) return undefined;

        setVisible(true);
        // Heads-ups stay a little longer so they can be read.
        const timer = setTimeout(() => setVisible(false), flash?.warning ? 8000 : 3500);

        return () => clearTimeout(timer);
    }, [flash]); // eslint-disable-line react-hooks/exhaustive-deps

    if (!visible || !message) return null;

    return (
        <div
            role="status"
            className={`fixed bottom-6 right-6 z-50 max-w-md border border-console-line border-l-2 bg-console-panel px-4 py-3 text-sm text-console-text shadow-xl shadow-arka-navy/10 ${
                flash?.warning ? 'border-l-arka-gold' : 'border-l-arka-teal'
            }`}
        >
            {message}
        </div>
    );
}

/**
 * Shown once when an invite email could not be sent, so the login details can be shared another way.
 * It is not stored anywhere, so it disappears on the next page visit.
 */
export function IssuedInvitation() {
    const { flash } = usePage().props;
    const [copied, setCopied] = useState(false);
    const [dismissed, setDismissed] = useState(false);
    const invitation = flash?.invitation;

    useEffect(() => {
        setCopied(false);
        setDismissed(false);
    }, [invitation]);

    if (!invitation || dismissed) return null;

    const details = [
        `Username: ${invitation.email}`,
        `Default password: ${invitation.defaultPassword}`,
        `Verify email & log in: ${invitation.url}`,
    ].join('\n');

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(details);
            setCopied(true);
        } catch {
            setCopied(false);
        }
    };

    return (
        <section className="border border-console-line border-l-2 border-l-arka-gold bg-console-panel px-6 py-5">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0 flex-1">
                    <p className="text-[11px] font-medium uppercase tracking-[0.2em] text-console-muted">Login details · shown once</p>
                    <p className="mt-2 font-condensed text-lg font-semibold text-console-heading">
                        {invitation.name} · {invitation.employeeCode}
                    </p>
                    <dl className="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-xs">
                        <dt className="text-console-muted">Username</dt>
                        <dd className="select-all break-all font-mono text-console-text">{invitation.email}</dd>
                        <dt className="text-console-muted">Default password</dt>
                        <dd className="select-all break-all font-mono text-console-text">{invitation.defaultPassword}</dd>
                        <dt className="text-console-muted">Verify link</dt>
                        <dd className="select-all break-all font-mono text-console-text">{invitation.url}</dd>
                    </dl>
                    <p className="mt-3 text-xs text-console-dim">
                        The email to {invitation.email} didn't go through. Share these privately. They open the verify link first, then log in. The link works once and expires in 7 days.
                    </p>
                </div>
                <div className="flex gap-2">
                    <SecondaryButton onClick={copy} className="!px-3 !py-1.5 !text-xs">
                        {copied ? 'Copied' : 'Copy details'}
                    </SecondaryButton>
                    <button type="button" onClick={() => setDismissed(true)} className="px-2 text-xs text-console-muted hover:text-arka-teal">
                        Dismiss
                    </button>
                </div>
            </div>
        </section>
    );
}
