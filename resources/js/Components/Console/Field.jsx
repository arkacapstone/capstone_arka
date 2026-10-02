import { EyeIcon, EyeOffIcon } from '@/Components/Icons';
import { forwardRef, useState } from 'react';

const inputBase =
    'mt-2 block w-full rounded-none border bg-console-panel px-3 py-2.5 text-sm text-console-text placeholder:text-console-dim transition-colors hover:border-arka-aqua focus:border-arka-teal focus:ring-1 focus:ring-arka-teal';

function FieldError({ message }) {
    return message ? <p className="mt-2 text-xs text-console-error">{message}</p> : null;
}

function FieldLabel({ htmlFor, children }) {
    return (
        <label htmlFor={htmlFor} className="block text-[11px] font-medium uppercase tracking-[0.18em] text-console-muted">
            {children}
        </label>
    );
}

/** Labelled input with its validation message. Errors are outlined and written in red. */
const Field = forwardRef(function Field({ id, label, error, className = '', ...props }, ref) {
    return (
        <div className={className}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <input id={id} ref={ref} className={`${inputBase} ${error ? 'border-console-error hover:border-console-error focus:border-console-error focus:ring-console-error' : 'border-console-line'}`} {...props} />
            <FieldError message={error} />
        </div>
    );
});

export default Field;

/** Labelled password input with a show/hide toggle. */
export const PasswordField = forwardRef(function PasswordField({ id, label, error, className = '', ...props }, ref) {
    const [visible, setVisible] = useState(false);

    return (
        <div className={className}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <div className="relative">
                <input
                    id={id}
                    ref={ref}
                    {...props}
                    type={visible ? 'text' : 'password'}
                    className={`${inputBase} pr-11 ${error ? 'border-console-error hover:border-console-error focus:border-console-error focus:ring-console-error' : 'border-console-line'}`}
                />
                <button
                    type="button"
                    onClick={() => setVisible((value) => !value)}
                    className="absolute inset-y-0 right-0 mt-2 flex w-11 items-center justify-center text-console-muted transition-colors hover:text-arka-teal"
                    aria-label={visible ? 'Hide password' : 'Show password'}
                    title={visible ? 'Hide password' : 'Show password'}
                >
                    {visible ? <EyeOffIcon className="h-[18px] w-[18px]" /> : <EyeIcon className="h-[18px] w-[18px]" />}
                </button>
            </div>
            <FieldError message={error} />
        </div>
    );
});

/** Labelled select. `options` is a list of {value, label}. */
export function SelectField({ id, label, error, options, placeholder, className = '', ...props }) {
    return (
        <div className={className}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <select id={id} className={`${inputBase} ${error ? 'border-console-error hover:border-console-error focus:border-console-error focus:ring-console-error' : 'border-console-line'}`} {...props}>
                {placeholder !== undefined && <option value="">{placeholder}</option>}
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            <FieldError message={error} />
        </div>
    );
}

/** Labelled textarea. */
export function TextAreaField({ id, label, error, className = '', ...props }) {
    return (
        <div className={className}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <textarea id={id} rows={3} className={`${inputBase} ${error ? 'border-console-error hover:border-console-error focus:border-console-error focus:ring-console-error' : 'border-console-line'}`} {...props} />
            <FieldError message={error} />
        </div>
    );
}

/** The one primary action on a screen: solid teal. */
export function ConsoleButton({ className = '', children, ...props }) {
    return (
        <button
            {...props}
            className={`inline-flex items-center justify-center gap-2 border border-arka-teal bg-arka-teal px-4 py-2 text-sm font-medium text-white transition-colors hover:border-[#276E82] hover:bg-[#276E82] focus:outline-none focus-visible:ring-2 focus-visible:ring-arka-aqua focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${className}`}
        >
            {children}
        </button>
    );
}

/** Any second action: outlined. */
export function SecondaryButton({ className = '', children, ...props }) {
    return (
        <button
            type="button"
            {...props}
            className={`inline-flex items-center justify-center gap-2 border border-console-line bg-transparent px-4 py-2 text-sm font-medium text-console-heading transition-colors hover:border-arka-teal hover:text-arka-teal disabled:cursor-not-allowed disabled:opacity-50 ${className}`}
        >
            {children}
        </button>
    );
}

export function SavedNotice({ show, children = 'Saved.' }) {
    return (
        <span aria-live="polite" className={`text-sm text-arka-teal transition-opacity duration-300 ${show ? 'opacity-100' : 'opacity-0'}`}>
            {children}
        </span>
    );
}
