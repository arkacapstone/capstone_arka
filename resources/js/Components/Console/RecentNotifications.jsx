import { NotificationIcon, openNotification, useNotificationFeed } from '@/Components/Console/NotificationBell';
import Panel, { PanelHeading } from '@/Components/Console/Panel';
import { ArrowRightIcon } from '@/Components/Icons';
import { timeAgo } from '@/lib/format';
import { Link } from '@inertiajs/react';

/** Dashboard card: the last three notifications, each opening the page it is about. */
export default function RecentNotifications({ className = '', columns = 3 }) {
    const feed = useNotificationFeed();
    const latest = feed?.recent.slice(0, 3) ?? [];

    return (
        <Panel className={className}>
            <PanelHeading
                title="Recent notifications"
                subtitle={feed?.unreadCount ? `${feed.unreadCount} unread` : 'Nothing unread'}
                action={
                    <Link href={route('notifications.index')} className="group inline-flex items-center gap-1.5 text-sm font-medium text-arka-teal">
                        View all
                        <ArrowRightIcon className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                    </Link>
                }
            />
            {latest.length === 0 ? (
                <p className="mt-6 text-sm italic text-console-muted">No notifications yet.</p>
            ) : (
                <ul className={`mt-6 grid gap-3 ${columns === 3 ? 'md:grid-cols-3' : ''}`}>
                    {latest.map((item) => (
                        <li key={item.id}>
                            <button
                                type="button"
                                onClick={() => openNotification(item)}
                                className={`flex h-full w-full gap-3 border px-4 py-3 text-left transition-colors hover:border-arka-teal ${
                                    item.readAt ? 'border-console-line' : 'border-arka-teal/40 bg-arka-teal/5'
                                }`}
                            >
                                <NotificationIcon category={item.category} unread={!item.readAt} />
                                <span className="min-w-0">
                                    <span className="block text-sm font-medium text-console-heading">{item.title}</span>
                                    {item.message && <span className="mt-1 line-clamp-2 block text-xs leading-relaxed text-console-muted">{item.message}</span>}
                                    <span className="mt-2 block font-mono text-[11px] text-console-dim">{timeAgo(item.createdAt)}</span>
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}
