import { router } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from './ConfirmDialog';

/**
 * Confirms an activate/deactivate, a resent invite or a password reset for an account.
 * `routePrefix` is e.g. "super-admin.workforce.admins".
 */
export default function AccountActionDialog({ action, routePrefix, roleLabel, onClose }) {
    const [processing, setProcessing] = useState(false);

    if (!action) return null;

    const { account, type } = action;
    const deactivating = type === 'status' && account.status === 'active';

    const copies = {
        invite: {
            title: 'Resend invite?',
            body: `${account.name} gets a new default password and verify link at ${account.email}. The old ones stop working.`,
            confirm: 'Resend invite',
        },
        reset: {
            title: 'Reset password?',
            body: `${account.name} gets a new default password at ${account.email} and is signed out. They create their own password on their next login.`,
            confirm: 'Reset password',
        },
        deactivate: {
            title: `Deactivate ${roleLabel.toLowerCase()}?`,
            body: `${account.name} will be signed out and can no longer log in. Their records are kept.`,
            confirm: 'Deactivate',
        },
        activate: { title: `Activate ${roleLabel.toLowerCase()}?`, body: `${account.name} will be able to log in again.`, confirm: 'Activate' },
    };
    const copy = copies[type === 'status' ? (deactivating ? 'deactivate' : 'activate') : type];

    const options = {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => {
            setProcessing(false);
            onClose();
        },
    };

    const confirm = () => {
        if (type === 'invite') return router.post(route(`${routePrefix}.invitation`, account.id), {}, options);
        if (type === 'reset') return router.post(route(`${routePrefix}.password-reset`, account.id), {}, options);

        return router.patch(route(`${routePrefix}.status`, account.id), { status: deactivating ? 'inactive' : 'active' }, options);
    };

    return (
        <ConfirmDialog
            open
            title={copy.title}
            body={copy.body}
            confirmLabel={copy.confirm}
            danger={deactivating}
            processing={processing}
            onConfirm={confirm}
            onClose={onClose}
        />
    );
}

/** A forgotten password can be reset once the account is verified and active (invited accounts get "Resend invite"). */
export function canResetPassword(account) {
    return !account.invited && account.status === 'active';
}

export function AccountRowActions({ account, onEdit, onAction, children }) {
    const button = 'px-2 py-1 text-xs text-console-muted transition-colors hover:bg-console-raised hover:text-arka-teal';

    return (
        <div className="flex justify-end gap-1">
            {children}
            <button type="button" className={button} onClick={() => onEdit(account)}>
                Edit
            </button>
            {account.invited && (
                <button type="button" className={button} onClick={() => onAction({ type: 'invite', account })}>
                    Resend invite
                </button>
            )}
            {canResetPassword(account) && (
                <button type="button" className={button} onClick={() => onAction({ type: 'reset', account })}>
                    Reset password
                </button>
            )}
            <button
                type="button"
                className={`${button} ${account.status === 'active' ? 'hover:!text-console-heading' : 'hover:!text-arka-teal'}`}
                onClick={() => onAction({ type: 'status', account })}
            >
                {account.status === 'active' ? 'Deactivate' : 'Activate'}
            </button>
        </div>
    );
}
