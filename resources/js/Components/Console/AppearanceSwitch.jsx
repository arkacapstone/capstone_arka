import { MonitorIcon, MoonIcon, SunIcon } from '@/Components/Icons';
import { applyAppearance } from '@/lib/appearance';
import { router, usePage } from '@inertiajs/react';

export const appearanceOptions = [
    { value: 'light', label: 'Light', icon: SunIcon, hint: 'Paper ground, navy type.' },
    { value: 'dark', label: 'Dark', icon: MoonIcon, hint: 'Deep navy ground for low light.' },
    { value: 'system', label: 'System', icon: MonitorIcon, hint: "Follows your device's setting." },
];

/** Saves the appearance to the account and applies it straight away. */
export function saveAppearance(value) {
    applyAppearance(value);
    router.patch(route('profile.appearance'), { appearance: value }, { preserveScroll: true, preserveState: true });
}

/** Compact segmented Light / Dark / System control (profile menu). */
export default function AppearanceSwitch({ className = '' }) {
    const current = usePage().props.auth.user.appearance;

    return (
        <div role="radiogroup" aria-label="Appearance" className={`grid grid-cols-3 border border-console-line ${className}`}>
            {appearanceOptions.map(({ value, label, icon: Icon }) => {
                const active = current === value;

                return (
                    <button
                        key={value}
                        type="button"
                        role="radio"
                        aria-checked={active}
                        onClick={() => !active && saveAppearance(value)}
                        className={`flex items-center justify-center gap-1.5 px-2 py-1.5 text-xs transition-colors ${
                            active ? 'bg-arka-teal text-white' : 'text-console-muted hover:bg-console-raised hover:text-arka-teal'
                        }`}
                    >
                        <Icon className="h-3.5 w-3.5" />
                        {label}
                    </button>
                );
            })}
        </div>
    );
}
