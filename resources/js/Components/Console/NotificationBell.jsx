import { BellIcon, notificationIcons } from '@/Components/Icons';
import { timeAgo } from '@/lib/format';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const POLL_MS = 30_000;

/** Opens a notification: marks it read, then continues to the page it is about. */
export function openNotification(item) {
    router.post(route('notifications.read', item.id), { open: Boolean(item.url) }, { preserveScroll: !item.url });
}

export function NotificationIcon({ category, unread }) {
    const Icon = notificationIcons[category] ?? BellIcon;

    return (
        <span
            className={`flex h-8 w-8 shrink-0 items-center justify-center border ${
                unread ? 'border-arka-teal/40 bg-arka-teal/10 text-arka-teal' : 'border-console-line text-console-muted'
            }`}
        >
            <Icon className="h-4 w-4" />
        </span>
    );
}

/**
 * Keeps the shared `notifications` prop fresh without reloading the page: polls a small JSON
 * feed while the tab is visible, so new notifications appear for everyone on every screen.
 */
export function useNotificationFeed() {
    const { notifications } = usePage().props;
    const [feed, setFeed] = useState(notifications);

    useEffect(() => setFeed(notifications), [notifications]);

    useEffect(() => {
        if (!notifications) return undefined;

        const refresh = () => {
            if (document.visibilityState !== 'visible') return;

            window.axios
                .get(route('notifications.feed'))
                .then(({ data }) => setFeed(data))
                .catch(() => {
                    // Offline or signed out: keep what we have; the next visit refreshes it.
                });
        };

        const timer = setInterval(refresh, POLL_MS);
        document.addEventListener('visibilitychange', refresh);

        return () => {
            clearInterval(timer);
            document.removeEventListener('visibilitychange', refresh);
        };
    }, [Boolean(notifications)]); // eslint-disable-line react-hooks/exhaustive-deps

    return feed;
}

export default function NotificationBell() {
    const feed = useNotificationFeed();
    const [open, setOpen] = useState(false);
    const [pulse, setPulse] = useState(false);
    const previousUnread = useRef(feed?.unreadCount ?? 0);
    const wrapper = useRef(null);

    useEffect(() => {
        if (!open) return undefined;

        const close = (event) => {
            if (event.type === 'keydown' ? event.key === 'Escape' : !wrapper.current?.contains(event.target)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', close);

        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', close);
        };
    }, [open]);

    // A quiet pulse when something new arrives while the page is open.
    useEffect(() => {
        const unread = feed?.unreadCount ?? 0;

        if (unread > previousUnread.current) {
            setPulse(true);
            const timer = setTimeout(() => setPulse(false), 2400);
            previousUnread.current = unread;

            return () => clearTimeout(timer);
        }

        previousUnread.current = unread;

        return undefined;
    }, [feed?.unreadCount]);

    if (!feed) return null;

    const { unreadCount, recent } = feed;

    return (
        <div ref={wrapper} className="relative">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                className={`relative flex h-10 w-10 items-center justify-center border text-console-heading transition-colors hover:border-arka-teal hover:text-arka-teal ${
                    open ? 'border-arka-teal text-arka-teal' : 'border-console-line'
                }`}
                aria-label={`Notifications (${unreadCount} unread)`}
                aria-expanded={open}
            >
                <BellIcon className="h-5 w-5" />
                {unreadCount > 0 && (
                    // Gold badge: the one highlight on the top bar (brand guide).
                    <span className="absolute -right-2 -top-2 flex h-[18px] min-w-[18px] items-center justify-center bg-arka-gold px-1 font-mono text-[10px] font-bold leading-none text-arka-navy">
                        {pulse && <span className="absolute inset-0 animate-ping bg-arka-gold/70" />}
                        <span className="relative">{unreadCount > 9 ? '9+' : unreadCount}</span>
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 z-40 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] overflow-hidden border border-console-line bg-console-panel shadow-xl shadow-arka-navy/10">
                    <div className="flex items-center justify-between border-b border-console-line px-4 py-3">
                        <div>
                            <p className="font-condensed text-lg font-semibold leading-tight text-console-heading">Notifications</p>
                            <p className="font-mono text-[10px] uppercase tracking-[0.18em] text-console-muted">{unreadCount} unread</p>
                        </div>
                        {unreadCount > 0 && (
                            <button
                                type="button"
                                onClick={() => router.post(route('notifications.read-all'), {}, { preserveScroll: true })}
                                className="text-xs text-arka-teal hover:underline"
                            >
                                Mark all as read
                            </button>
                        )}
                    </div>

                    {recent.length === 0 ? (
                        <p className="px-4 py-8 text-center text-sm text-console-dim">You're all caught up.</p>
                    ) : (
                        <ul className="max-h-96 divide-y divide-console-line overflow-y-auto">
                            {recent.map((item) => (
                                <li key={item.id}>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setOpen(false);
                                            openNotification(item);
                                        }}
                                        className="flex w-full gap-3 px-4 py-3 text-left transition-colors hover:bg-console-raised"
                                    >
                                        <NotificationIcon category={item.category} unread={!item.readAt} />
                                        <span className="min-w-0 flex-1">
                                            <span className={`block truncate text-sm ${item.readAt ? 'text-console-muted' : 'font-medium text-console-heading'}`}>
                                                {item.title}
                                            </span>
                                            {item.message && <span className="mt-0.5 line-clamp-2 block text-xs leading-snug text-console-muted">{item.message}</span>}
                                            <span className="mt-1 block font-mono text-[10px] text-console-dim">{timeAgo(item.createdAt)}</span>
                                        </span>
                                        {!item.readAt && <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-arka-teal" aria-label="Unread" />}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}

                    <Link
                        href={route('notifications.index')}
                        className="block border-t border-console-line px-4 py-2.5 text-center text-xs text-arka-teal transition-colors hover:bg-console-raised"
                    >
                        View all notifications
                    </Link>
                </div>
            )}
        </div>
    );
}
