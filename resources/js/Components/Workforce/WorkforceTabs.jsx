import { Link } from '@inertiajs/react';

const tabs = [
    { label: 'Admins', route: 'super-admin.workforce.admins.index', match: 'super-admin.workforce.admins.*' },
    { label: 'Contractors', route: 'super-admin.workforce.employees.index', match: 'super-admin.workforce.employees.*' },
    { label: 'Clients', route: 'super-admin.workforce.clients.index', match: 'super-admin.workforce.clients.*' },
    { label: 'Devices', route: 'super-admin.workforce.devices.index', match: 'super-admin.workforce.devices.*' },
];

export default function WorkforceTabs() {
    return (
        <nav className="flex gap-6 overflow-x-auto border-b border-console-line [scrollbar-width:none]" aria-label="Workforce sections">
            {tabs.map((tab) => {
                const active = route().current(tab.match);

                return (
                    <Link
                        key={tab.route}
                        href={route(tab.route)}
                        className={`-mb-px whitespace-nowrap border-b-2 pb-3 font-condensed text-[17px] font-semibold transition-colors ${
                            active ? 'border-arka-teal text-console-text' : 'border-transparent text-console-muted hover:text-arka-teal'
                        }`}
                    >
                        {tab.label}
                    </Link>
                );
            })}
        </nav>
    );
}
