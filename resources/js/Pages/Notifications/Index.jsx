import { SecondaryButton } from '@/Components/Console/Field';
import { NotificationIcon, openNotification, useNotificationFeed } from '@/Components/Console/NotificationBell';
import Pagination from '@/Components/Console/Pagination';
import Panel, { PanelHeading } from '@/Components/Console/Panel';
import { ArrowRightIcon } from '@/Components/Icons';
import AppLayout from '@/Layouts/AppLayout';
import { timeAgo } from '@/lib/format';
import { Link, router } from '@inertiajs/react';

function when(iso) {
    return new Date(iso).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

export default function Index({ items, filter }) {
    const feed = useNotificationFeed();

    return (
        <AppLayout title="Notifications" eyebrow="Notification center">
            <div className="mx-auto max-w-4xl">
                <Panel>
                    <PanelHeading
                        title="All notifications"
                        subtitle="Updates about your work and account. Notifications never replace the records themselves."
                        action={
                            feed?.unreadCount > 0 && (
                                <SecondaryButton onClick={() => router.post(route('notifications.read-all'), {}, { preserveScroll: true })}>
                                    Mark all as read
                                </SecondaryButton>
                            )
                        }
                    />

                    <div className="mt-6 flex gap-1 border-b border-console-line">
                        {[
                            ['all', 'All'],
                            ['unread', `Unread${feed?.unreadCount ? ` (${feed.unreadCount})` : ''}`],
                        ].map(([value, label]) => (
                            <Link
                                key={value}
                                href={route('notifications.index', value === 'all' ? {} : { filter: value })}
                                preserveScroll
                                className={`-mb-px border-b-2 px-4 py-2 font-condensed text-[15px] font-semibold transition-colors ${
                                    filter === value ? 'border-arka-teal text-arka-teal' : 'border-transparent text-console-muted hover:text-arka-teal'
                                }`}
                            >
                                {label}
                            </Link>
                        ))}
                    </div>

                    {items.data.length === 0 ? (
                        <p className="py-10 text-center font-condensed text-[15px] italic text-console-muted">
                            {filter === 'unread' ? 'Nothing unread — you are all caught up.' : "You're all caught up."}
                        </p>
                    ) : (
                        <ul>
                            {items.data.map((item) => (
                                <li key={item.id} className="border-b border-console-line">
                                    <button
                                        type="button"
                                        onClick={() => openNotification(item)}
                                        className="group flex w-full gap-4 px-2 py-4 text-left transition-colors hover:bg-console-raised"
                                    >
                                        <NotificationIcon category={item.category} unread={!item.readAt} />
                                        <span className="min-w-0 flex-1">
                                            <span className={`block ${item.readAt ? 'text-console-muted' : 'font-medium text-console-heading'}`}>{item.title}</span>
                                            {item.message && <span className="mt-1 block text-sm text-console-muted">{item.message}</span>}
                                            <span className="mt-1.5 block font-mono text-[11px] text-console-dim" title={when(item.createdAt)}>
                                                {when(item.createdAt)} · {timeAgo(item.createdAt)}
                                            </span>
                                        </span>
                                        {item.url && (
                                            <ArrowRightIcon className="mt-1 h-4 w-4 shrink-0 text-console-dim transition-transform group-hover:translate-x-0.5 group-hover:text-arka-teal" />
                                        )}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}

                    <div className="mt-5">
                        <Pagination meta={items.meta} />
                    </div>
                </Panel>
            </div>
        </AppLayout>
    );
}
