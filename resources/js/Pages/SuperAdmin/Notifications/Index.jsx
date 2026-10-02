import Field, { ConsoleButton, SecondaryButton, TextAreaField } from '@/Components/Console/Field';
import { NotificationIcon, openNotification, useNotificationFeed } from '@/Components/Console/NotificationBell';
import Panel, { PanelHeading } from '@/Components/Console/Panel';
import RuleGroupForm from '@/Components/Console/RuleGroupForm';
import { ArrowRightIcon } from '@/Components/Icons';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { timeAgo } from '@/lib/format';
import { Link, router, useForm } from '@inertiajs/react';

function AnnouncementForm({ audiences }) {
    const { data, setData, post, processing, errors, reset } = useForm({ heading: '', body: '', audience: 'everyone' });

    const submit = (e) => {
        e.preventDefault();
        post(route('super-admin.notifications.announce'), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <Panel>
            <PanelHeading title="Post an announcement" subtitle="Sent as an in-app notification. There is no email, SMS or push." />
            <form onSubmit={submit} className="mt-6 flex flex-col gap-5">
                <fieldset>
                    <legend className="block text-[11px] font-medium uppercase tracking-[0.18em] text-console-muted">Send to</legend>
                    <div className="mt-2 flex flex-wrap gap-2">
                        {audiences.map((audience) => (
                            <button
                                key={audience.value}
                                type="button"
                                onClick={() => setData('audience', audience.value)}
                                className={`border px-3 py-1.5 text-sm transition-colors ${
                                    data.audience === audience.value
                                        ? 'border-arka-teal bg-arka-teal/10 text-arka-teal'
                                        : 'border-console-line text-console-muted hover:border-arka-teal hover:text-arka-teal'
                                }`}
                            >
                                {audience.label} <span className="font-mono text-xs">({audience.count})</span>
                            </button>
                        ))}
                    </div>
                    {errors.audience && <p className="mt-2 text-xs text-console-error">{errors.audience}</p>}
                </fieldset>
                <Field id="heading" label="Heading" maxLength={120} value={data.heading} onChange={(e) => setData('heading', e.target.value)} error={errors.heading} required />
                <TextAreaField id="body" label="Message" rows={4} maxLength={2000} value={data.body} onChange={(e) => setData('body', e.target.value)} error={errors.body} required />
                <div>
                    <ConsoleButton type="submit" disabled={processing}>
                        Send announcement
                    </ConsoleButton>
                </div>
            </form>
        </Panel>
    );
}

export default function Index({ filter, inbox, announcements, audiences, settings }) {
    const feed = useNotificationFeed();

    return (
        <SuperAdminLayout title="Notifications">
            <div className="mx-auto grid max-w-[1560px] gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div className="flex flex-col gap-8">
                    <Panel>
                        <PanelHeading
                            title="Your alerts"
                            subtitle="Approvals, payroll, requests and system alerts that need the Super Admin."
                            action={
                                feed?.unreadCount > 0 && (
                                    <SecondaryButton onClick={() => router.post(route('notifications.read-all'), {}, { preserveScroll: true })}>Mark all as read</SecondaryButton>
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
                                    href={route('super-admin.notifications', value === 'all' ? {} : { filter: value })}
                                    preserveScroll
                                    className={`-mb-px border-b-2 px-4 py-2 font-condensed text-[15px] font-semibold transition-colors ${
                                        filter === value ? 'border-arka-teal text-arka-teal' : 'border-transparent text-console-muted hover:text-arka-teal'
                                    }`}
                                >
                                    {label}
                                </Link>
                            ))}
                        </div>

                        {inbox.length === 0 ? (
                            <p className="py-10 text-center font-condensed text-[15px] italic text-console-muted">
                                {filter === 'unread' ? 'Nothing unread — you are all caught up.' : "You're all caught up."}
                            </p>
                        ) : (
                            <ul>
                                {inbox.map((item) => (
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
                                                <span className="mt-1.5 block font-mono text-[11px] text-console-dim">{timeAgo(item.createdAt)}</span>
                                            </span>
                                            {item.url && (
                                                <ArrowRightIcon className="mt-1 h-4 w-4 shrink-0 text-console-dim transition-transform group-hover:translate-x-0.5 group-hover:text-arka-teal" />
                                            )}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}

                        <Link href={route('notifications.index')} className="mt-5 inline-flex items-center gap-2 text-sm text-arka-teal hover:underline">
                            Full notification history <ArrowRightIcon className="h-4 w-4" />
                        </Link>
                    </Panel>

                    <RuleGroupForm
                        title="Notification settings"
                        subtitle="System-wide reminders ARKA sends on a schedule. The scheduler checks every 15 minutes."
                        rules={settings}
                    />
                </div>

                <div className="flex flex-col gap-8">
                    <AnnouncementForm audiences={audiences} />

                    <Panel>
                        <PanelHeading title="Recent announcements" />
                        {announcements.length === 0 ? (
                            <p className="py-8 text-center font-condensed text-[15px] italic text-console-muted">No announcements yet.</p>
                        ) : (
                            <ul className="mt-4">
                                {announcements.map((announcement) => (
                                    <li key={announcement.id} className="border-b border-console-line py-3">
                                        <p className="text-sm text-console-heading">{announcement.details}</p>
                                        <p className="mt-1 font-mono text-[11px] text-console-dim">
                                            {announcement.by ?? 'System'} · {timeAgo(announcement.postedAt)}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>
            </div>
        </SuperAdminLayout>
    );
}
