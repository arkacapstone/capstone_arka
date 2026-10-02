import Field, { ConsoleButton, SelectField } from '@/Components/Console/Field';
import { birthdayRange } from '@/lib/format';
import { useForm } from '@inertiajs/react';

/**
 * Create/edit form for Admin and Contractor accounts. On create only the basics are asked:
 * the account owner fills in their own personal details on first login.
 * Pass `employmentTypes` to include the Full-Time / Part-Time choice.
 */
export default function AccountForm({ account, storeUrl, updateUrl, employmentTypes, submitLabel, onDone }) {
    const editing = Boolean(account);
    const { data, setData, post, put, processing, errors } = useForm({
        name: account?.name ?? '',
        email: account?.email ?? '',
        ...(editing ? { phone_number: account.phoneNumber ?? '', birthday: account.birthday ?? '' } : {}),
        ...(employmentTypes ? { employment_type: account?.employmentType ?? employmentTypes[0].value } : {}),
    });

    const submit = (e) => {
        e.preventDefault();

        const options = { preserveScroll: true, onSuccess: onDone };

        editing ? put(updateUrl, options) : post(storeUrl, options);
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-5">
            <Field id="name" label="Full name" value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} autoFocus required />
            <Field
                id="email"
                type="email"
                label="Email (Gmail)"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                error={errors.email}
                placeholder="name@gmail.com"
                required
            />
            {!editing && <p className="-mt-3 text-xs text-console-muted">Their personal email. Their login details are sent here, and it is their username.</p>}
            {employmentTypes && (
                <SelectField
                    id="employment_type"
                    label="Employment type"
                    value={data.employment_type}
                    onChange={(e) => setData('employment_type', e.target.value)}
                    error={errors.employment_type}
                    options={employmentTypes}
                />
            )}
            {editing && (
                <>
                    <Field
                        id="phone_number"
                        label="Phone number (optional)"
                        value={data.phone_number}
                        onChange={(e) => setData('phone_number', e.target.value)}
                        error={errors.phone_number}
                        placeholder="09XX XXX XXXX"
                    />
                    <Field
                        id="birthday"
                        type="date"
                        label="Birthday (optional)"
                        value={data.birthday}
                        onChange={(e) => setData('birthday', e.target.value)}
                        error={errors.birthday}
                        {...birthdayRange()}
                    />
                </>
            )}
            {!editing && (
                <p className="border border-console-line bg-console-panel px-4 py-3 text-xs leading-relaxed text-console-muted">
                    ARKA assigns the ID and emails their username and a default password, with a button to verify their email (it works once and expires in 7 days).
                    After verifying they log in, create their own password, and fill in their phone, birthday, address and emergency contact.
                </p>
            )}

            <div className="flex items-center gap-3">
                <ConsoleButton disabled={processing}>{editing ? 'Save changes' : submitLabel}</ConsoleButton>
                <button type="button" onClick={onDone} className="text-[13px] text-console-muted hover:text-arka-teal">
                    Cancel
                </button>
            </div>
        </form>
    );
}
