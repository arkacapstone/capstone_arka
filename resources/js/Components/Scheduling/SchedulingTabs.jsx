import { Link } from '@inertiajs/react';

/** Admin Scheduling: give contractors their clients first, then schedule them. */
const tabs = [
    { label: 'Clients', route: 'admin.scheduling.clients.index', match: 'admin.scheduling.clients.*' },
    { label: 'Schedules', route: 'admin.scheduling.index', match: 'admin.scheduling.index' },
];

export default function SchedulingTabs() {
    return (
        <nav className="flex gap-6 overflow-x-auto border-b border-console-line [scrollbar-width:none]" aria-label="Scheduling sections">
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
