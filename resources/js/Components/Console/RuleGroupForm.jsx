import Field, { ConsoleButton } from '@/Components/Console/Field';
import Panel, { PanelHeading } from '@/Components/Console/Panel';
import { useForm } from '@inertiajs/react';

/** One System & Rules section: each rule rendered from its definition, saved together. */
export default function RuleGroupForm({ title, subtitle, rules }) {
    const initial = Object.fromEntries(rules.map((rule) => [rule.key, rule.type === 'boolean' ? Boolean(rule.value) : String(rule.value ?? '')]));
    const { data, setData, put, processing, errors, isDirty, setDefaults } = useForm(initial);

    const submit = (e) => {
        e.preventDefault();
        put(route('super-admin.rules.update'), { preserveScroll: true, onSuccess: () => setDefaults() });
    };

    return (
        <Panel>
            <PanelHeading title={title} subtitle={subtitle} />
            <form onSubmit={submit} className="mt-6">
                <div className="grid gap-x-8 gap-y-6 md:grid-cols-2">
                    {rules.map((rule) =>
                        rule.type === 'boolean' ? (
                            <label key={rule.key} htmlFor={rule.key} className="flex cursor-pointer items-start gap-3 border border-console-line px-4 py-3 hover:border-arka-teal">
                                <input
                                    id={rule.key}
                                    type="checkbox"
                                    checked={data[rule.key]}
                                    onChange={(e) => setData(rule.key, e.target.checked)}
                                    className="mt-0.5 rounded-none border-console-line text-arka-teal focus:ring-arka-teal"
                                />
                                <span>
                                    <span className="block text-sm font-medium text-console-heading">{rule.label}</span>
                                    <span className="mt-0.5 block text-xs text-console-muted">{rule.description}</span>
                                    {errors[rule.key] && <span className="mt-1 block text-xs text-console-error">{errors[rule.key]}</span>}
                                </span>
                            </label>
                        ) : (
                            <div key={rule.key}>
                                <Field
                                    id={rule.key}
                                    label={rule.label}
                                    type={{ integer: 'number', decimal: 'number', time: 'time' }[rule.type] ?? 'text'}
                                    min={rule.min ?? undefined}
                                    max={rule.max ?? undefined}
                                    step={rule.type === 'decimal' ? '0.01' : rule.type === 'integer' ? '1' : undefined}
                                    value={data[rule.key]}
                                    onChange={(e) => setData(rule.key, e.target.value)}
                                    error={errors[rule.key]}
                                    required
                                />
                                <p className="mt-1.5 text-xs text-console-dim">{rule.description}</p>
                            </div>
                        ),
                    )}
                </div>
                <div className="mt-6 flex items-center gap-4">
                    <ConsoleButton type="submit" disabled={processing || !isDirty}>
                        Save {title.toLowerCase()}
                    </ConsoleButton>
                    {isDirty && <span className="text-xs text-console-muted">Unsaved changes</span>}
                </div>
            </form>
        </Panel>
    );
}
