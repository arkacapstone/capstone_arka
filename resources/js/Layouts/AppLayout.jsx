import AppearanceSwitch from '@/Components/Console/AppearanceSwitch';
import { FlashToast } from '@/Components/Console/Flash';
import NotificationBell from '@/Components/Console/NotificationBell';
import { ChevronRightIcon, MenuIcon, PanelIcon, SwitchIcon, UserIcon, moduleIcons } from '@/Components/Icons';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const COLLAPSE_KEY = 'arka.sidebar.collapsed';

const portalLabels = {
    super_admin: 'Super Admin Portal',
    admin: 'Admin Portal',
    employee: 'Contractor Portal',
};

const dashboardLabels = {
    super_admin: 'Super Admin dashboard',
    admin: 'Admin dashboard',
    employee: 'Contractor dashboard',
};

function readCollapsed() {
    try {
        return window.localStorage.getItem(COLLAPSE_KEY) === '1';
    } catch {
        return false;
    }
}

function SailMark({ className = '' }) {
    return (
        <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
            <path d="M5 18 L12 4 L13.5 7 L8.5 18 Z" className="fill-console-heading" />
            <path d="M13 6 L19 18 L15 18 L11.8 10.5 Z" fill="#F0A81E" />
            <path d="M3 20 Q7.5 17.5 12 20 T21 20" fill="none" stroke="#2F8299" strokeWidth="1.6" strokeLinecap="round" />
        </svg>
    );
}

function initialsOf(name) {
    return name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function isActive(item) {
    return route().current(item.routeName) || route().current(`${item.routeName}.*`) || (item.match && route().current(item.match));
}

function NavItem({ item, collapsed, onNavigate, nested = false }) {
    const ItemIcon = moduleIcons[item.key] ?? UserIcon;
    const active = isActive(item);

    return (
        <Link
            href={item.href}
            onClick={onNavigate}
            title={collapsed ? item.label : undefined}
            aria-current={active ? 'page' : undefined}
            className={`group flex items-center gap-3 border-l-2 px-3 py-2.5 font-condensed text-[17px] font-semibold tracking-wide transition-colors duration-150 ${
                active
                    ? 'border-arka-teal bg-console-raised text-arka-teal'
                    : 'border-transparent text-console-heading hover:bg-console-raised hover:text-arka-teal'
            } ${collapsed ? 'justify-center' : ''} ${nested && !collapsed ? 'pl-9 text-[15px]' : ''}`}
        >
            <ItemIcon className="h-[18px] w-[18px] shrink-0" />
            {!collapsed && <span className="truncate">{item.label}</span>}
        </Link>
    );
}

/** A module with sub-pages (e.g. Time Tracker → Time History): the arrow opens and closes the sub-pages. */
function NavGroup({ item, collapsed, onNavigate }) {
    const childActive = item.children.some(isActive);
    const [open, setOpen] = useState(childActive);

    return (
        <div>
            <div className="relative">
                <NavItem item={item} collapsed={collapsed} onNavigate={onNavigate} />
                {!collapsed && (
                    <button
                        type="button"
                        onClick={() => setOpen((value) => !value)}
                        aria-expanded={open}
                        aria-label={`${open ? 'Hide' : 'Show'} ${item.label} pages`}
                        className="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-console-muted transition-colors hover:text-arka-teal"
                    >
                        <ChevronRightIcon className={`h-4 w-4 transition-transform duration-200 ${open ? 'rotate-90' : ''}`} />
                    </button>
                )}
            </div>
            {(open || collapsed) && item.children.map((child) => <NavItem key={child.key} item={child} collapsed={collapsed} onNavigate={onNavigate} nested />)}
        </div>
    );
}

function Sidebar({ collapsed, onNavigate }) {
    const { navigation, viewMode } = usePage().props;
    // Profile sits at the bottom of the sidebar; signing out is in the account menu (top right).
    const profile = navigation.find((item) => item.key === 'profile');
    const modules = navigation.filter((item) => item.key !== 'profile');

    return (
        <div className="flex h-full flex-col">
            <Link
                href={route('dashboard')}
                className={`flex h-[72px] items-center gap-3 border-b border-console-line px-5 ${collapsed ? 'justify-center px-0' : ''}`}
            >
                <span className="flex h-9 w-9 shrink-0 items-center justify-center border border-arka-teal/50">
                    <SailMark className="h-5 w-5" />
                </span>
                {!collapsed && (
                    <span className="min-w-0">
                        <span className="block font-condensed text-xl font-bold leading-tight tracking-[0.2em] text-console-heading">ARKA</span>
                        <span className="block truncate font-mono text-[10px] uppercase leading-tight tracking-[0.18em] text-console-muted">
                            {portalLabels[viewMode] ?? 'Portal'}
                        </span>
                    </span>
                )}
            </Link>

            <nav className="flex flex-1 flex-col gap-0.5 overflow-y-auto px-2 pb-4 pt-4">
                {modules.map((item) =>
                    item.children?.length ? (
                        <NavGroup key={item.key} item={item} collapsed={collapsed} onNavigate={onNavigate} />
                    ) : (
                        <NavItem key={item.key} item={item} collapsed={collapsed} onNavigate={onNavigate} />
                    ),
                )}
            </nav>

            {profile && (
                <div className="border-t border-console-line px-2 py-4">
                    <NavItem item={profile} collapsed={collapsed} onNavigate={onNavigate} />
                </div>
            )}
        </div>
    );
}

/** "FRI 25 SEP 2026 · 2:14 PM" */
function LiveClock() {
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const timer = setInterval(() => setNow(new Date()), 15_000);

        return () => clearInterval(timer);
    }, []);

    const date = now.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }).replaceAll(',', '');
    const time = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });

    return (
        <p className="hidden whitespace-nowrap font-mono text-xs uppercase tracking-wider text-console-muted xl:block">
            {date} · {time}
        </p>
    );
}

/** Admin only: Admin view ↔ the Admin's own Contractor view, same session (Admin flow §X). */
function DashboardSwitcher() {
    const { canSwitchView, viewMode } = usePage().props;
    const [switching, setSwitching] = useState(false);

    if (!canSwitchView) return null;

    const toEmployee = viewMode !== 'employee';

    return (
        <button
            type="button"
            disabled={switching}
            onClick={() =>
                router.post(
                    route('view-mode.switch'),
                    { mode: toEmployee ? 'employee' : 'admin' },
                    { onStart: () => setSwitching(true), onFinish: () => setSwitching(false) },
                )
            }
            className="inline-flex h-10 items-center gap-2 border border-console-line px-3 font-condensed text-[15px] font-semibold text-console-heading transition-colors hover:border-arka-teal hover:text-arka-teal disabled:opacity-60 sm:px-4"
            title={toEmployee ? 'Switch to Contractor View' : 'Switch to Admin View'}
        >
            <SwitchIcon className="h-4 w-4" />
            <span className="hidden md:inline">{toEmployee ? 'Switch to Contractor View' : 'Switch to Admin View'}</span>
        </button>
    );
}

function ProfileMenu() {
    const { auth, viewMode } = usePage().props;
    const [open, setOpen] = useState(false);
    const wrapper = useRef(null);

    useEffect(() => {
        if (!open) return undefined;

        const close = (event) => {
            if (event.type === 'keydown' ? event.key === 'Escape' : !wrapper.current?.contains(event.target)) setOpen(false);
        };

        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', close);

        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', close);
        };
    }, [open]);

    const roleLine = auth.user.role === 'company_admin' && viewMode === 'employee' ? 'Admin · Contractor view' : auth.user.roleLabel;

    return (
        <div ref={wrapper} className="relative">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                className="flex items-center gap-3 px-1.5 py-1 transition-colors hover:bg-console-raised"
                aria-expanded={open}
                aria-haspopup="menu"
            >
                <span className="flex h-9 w-9 items-center justify-center border border-arka-teal/30 bg-console-avatar font-condensed text-sm font-bold text-console-heading">
                    {initialsOf(auth.user.name)}
                </span>
                <span className="hidden text-left sm:block">
                    <span className="block max-w-[160px] truncate font-condensed text-[15px] font-semibold leading-tight text-console-heading">{auth.user.name}</span>
                    <span className="block font-mono text-[10px] uppercase leading-tight tracking-[0.18em] text-console-muted">{roleLine}</span>
                </span>
            </button>

            {open && (
                <div role="menu" className="absolute right-0 z-40 mt-2 w-64 border border-console-line bg-console-panel shadow-xl shadow-arka-navy/10">
                    <div className="flex items-center gap-3 border-b border-console-line px-4 py-3">
                        <span className="flex h-10 w-10 items-center justify-center border border-arka-teal/30 bg-console-avatar font-condensed font-bold text-console-heading">
                            {initialsOf(auth.user.name)}
                        </span>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium text-console-heading">{auth.user.name}</p>
                            <p className="text-xs text-console-muted">{roleLine}</p>
                        </div>
                    </div>
                    <Link href={route('profile.edit')} className="block px-4 py-2.5 text-sm text-console-text hover:bg-console-raised hover:text-arka-teal">
                        View / Edit Profile
                    </Link>
                    <Link
                        href={route('profile.security')}
                        className="block px-4 py-2.5 text-sm text-console-text hover:bg-console-raised hover:text-arka-teal"
                    >
                        Change Password
                    </Link>
                    <div className="border-t border-console-line px-4 py-3">
                        <p className="mb-2 text-[10px] font-medium uppercase tracking-[0.2em] text-console-muted">Appearance</p>
                        <AppearanceSwitch />
                    </div>
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="block w-full border-t border-console-line px-4 py-2.5 text-left text-sm text-console-text hover:bg-console-raised hover:text-arka-teal"
                    >
                        Sign Out
                    </Link>
                </div>
            )}
        </div>
    );
}

/**
 * The one authenticated shell for Super Admin, Admin and Contractor.
 * Navigation comes from the server per role and view, so every role shares one visual system.
 */
export default function AppLayout({ title, eyebrow, children }) {
    const { viewMode } = usePage().props;
    const [collapsed, setCollapsed] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);

    useEffect(() => setCollapsed(readCollapsed()), []);

    const toggleCollapsed = () => {
        setCollapsed((value) => {
            try {
                window.localStorage.setItem(COLLAPSE_KEY, value ? '0' : '1');
            } catch {
                // Storage can be unavailable (private mode); the toggle still works for this visit.
            }

            return !value;
        });
    };

    const label = eyebrow ?? dashboardLabels[viewMode] ?? 'Dashboard';

    return (
        <div className="min-h-screen bg-console-bg font-barlow text-console-text">
            <Head title={title} />

            <aside
                className={`fixed inset-y-0 left-0 z-30 hidden border-r border-console-line bg-console-panel transition-[width] duration-200 print:!hidden lg:block ${
                    collapsed ? 'w-[72px]' : 'w-[264px]'
                }`}
            >
                <Sidebar collapsed={collapsed} />
            </aside>

            {mobileOpen && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <button type="button" aria-label="Close menu" className="absolute inset-0 bg-arka-navy/40" onClick={() => setMobileOpen(false)} />
                    <aside className="absolute inset-y-0 left-0 w-[264px] border-r border-console-line bg-console-panel">
                        <Sidebar collapsed={false} onNavigate={() => setMobileOpen(false)} />
                    </aside>
                </div>
            )}

            <div className={`transition-[padding] duration-200 print:!pl-0 ${collapsed ? 'lg:pl-[72px]' : 'lg:pl-[264px]'}`}>
                <header className="sticky top-0 z-20 flex h-[72px] items-center justify-between gap-4 border-b border-console-line bg-console-panel/95 px-4 backdrop-blur print:hidden sm:px-6 lg:px-8">
                    <div className="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            onClick={() => setMobileOpen(true)}
                            className="p-1.5 text-console-heading transition-colors hover:bg-console-raised lg:hidden"
                            aria-label="Open menu"
                        >
                            <MenuIcon />
                        </button>
                        <button
                            type="button"
                            onClick={toggleCollapsed}
                            className="hidden p-1.5 text-console-muted transition-colors hover:bg-console-raised hover:text-arka-teal lg:block"
                            aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                        >
                            <PanelIcon />
                        </button>
                        <div className="min-w-0">
                            <p className="truncate font-mono text-[10px] font-medium uppercase tracking-[0.25em] text-arka-teal">{label}</p>
                            <h1 className="truncate font-condensed text-2xl font-bold leading-tight text-console-heading">{title}</h1>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 sm:gap-3">
                        <LiveClock />
                        <NotificationBell />
                        <DashboardSwitcher />
                        <span aria-hidden="true" className="mx-1 hidden h-9 w-px bg-console-line sm:block" />
                        <ProfileMenu />
                    </div>
                </header>

                <main className="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">{children}</main>
            </div>

            <FlashToast />
        </div>
    );
}
