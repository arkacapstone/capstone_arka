import { ConsoleButton } from '@/Components/Console/Field';
import Panel, { PanelHeading } from '@/Components/Console/Panel';
import { SendIcon, TrashIcon } from '@/Components/Icons';
import AppLayout from '@/Layouts/AppLayout';
import { router, useForm, usePoll } from '@inertiajs/react';
import { Fragment, useEffect, useRef } from 'react';

// New messages from others show up within a few seconds (no websocket server needed).
const REFRESH_MS = 4_000;

function initialsOf(name) {
    return name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function dayLabel(iso) {
    const date = new Date(iso);
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);

    if (date.toDateString() === today.toDateString()) return 'Today';
    if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';

    return date.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' });
}

function timeLabel(iso) {
    return new Date(iso).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

function MessageRow({ message, showSender }) {
    const remove = () => {
        if (window.confirm('Delete this message?')) {
            router.delete(route('community.messages.destroy', message.id), { preserveScroll: true });
        }
    };

    return (
        <li className={`group flex gap-3 ${message.mine ? 'flex-row-reverse' : ''} ${showSender ? 'mt-4' : 'mt-1'}`}>
            {/* Avatar only on the first message of a run from the same person. */}
            <span
                aria-hidden="true"
                className={`flex h-9 w-9 shrink-0 items-center justify-center border border-arka-teal/30 bg-console-avatar font-condensed text-sm font-bold text-console-heading ${showSender ? '' : 'invisible'}`}
            >
                {initialsOf(message.sender.name)}
            </span>

            <div className={`flex max-w-[75%] flex-col ${message.mine ? 'items-end' : 'items-start'}`}>
                {showSender && (
                    <p className="mb-1 text-xs text-console-muted">
                        <span className="font-semibold text-console-heading">{message.mine ? 'You' : message.sender.name}</span>
                        {' · '}
                        {message.sender.role}
                    </p>
                )}
                <div className={`flex items-end gap-2 ${message.mine ? 'flex-row-reverse' : ''}`}>
                    <p
                        className={`whitespace-pre-wrap break-words px-3.5 py-2 text-sm ${
                            message.mine ? 'bg-arka-teal text-white' : 'border border-console-line bg-console-raised text-console-text'
                        }`}
                    >
                        {message.body}
                    </p>
                    <span className="shrink-0 pb-1 font-mono text-[10px] text-console-dim">{timeLabel(message.at)}</span>
                    {message.canDelete && (
                        <button
                            type="button"
                            onClick={remove}
                            className="shrink-0 pb-1 text-console-dim opacity-0 transition-opacity hover:text-console-error focus:opacity-100 group-hover:opacity-100"
                            aria-label="Delete message"
                            title="Delete message"
                        >
                            <TrashIcon className="h-4 w-4" />
                        </button>
                    )}
                </div>
            </div>
        </li>
    );
}

/**
 * Community Chat: one room for everyone in the home office (Super Admin, Admins, Contractors).
 */
export default function Index({ messages, memberCount }) {
    const { data, setData, post, processing, errors, reset } = useForm({ body: '' });
    const listRef = useRef(null);
    const nearBottom = useRef(true);

    usePoll(REFRESH_MS, { only: ['messages'] });

    // Stay at the newest message, unless the user scrolled up to read older ones.
    useEffect(() => {
        const list = listRef.current;
        if (list && nearBottom.current) {
            list.scrollTop = list.scrollHeight;
        }
    }, [messages]);

    const onScroll = () => {
        const list = listRef.current;
        nearBottom.current = list.scrollHeight - list.scrollTop - list.clientHeight < 80;
    };

    const send = (e) => {
        e?.preventDefault();

        if (data.body.trim() === '' || processing) return;

        nearBottom.current = true;
        post(route('community.messages.store'), {
            preserveScroll: true,
            onSuccess: () => reset('body'),
        });
    };

    // Enter sends; Shift+Enter adds a new line.
    const onKeyDown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            send(e);
        }
    };

    return (
        <AppLayout title="Community Chat" eyebrow="Your home office">
            <div className="mx-auto max-w-4xl">
                <Panel className="flex h-[calc(100vh-11rem)] min-h-[28rem] flex-col">
                    <PanelHeading title="Community Chat" subtitle={`Everyone in your home office · ${memberCount} ${memberCount === 1 ? 'member' : 'members'}`} />

                    <div ref={listRef} onScroll={onScroll} className="mt-5 flex-1 overflow-y-auto border-y border-console-line py-4 pr-1">
                        {messages.length === 0 ? (
                            <p className="mt-10 text-center text-sm italic text-console-muted">No messages yet. Say hello to your team!</p>
                        ) : (
                            <ul>
                                {messages.map((message, index) => {
                                    const previous = messages[index - 1];
                                    const newDay = !previous || dayLabel(previous.at) !== dayLabel(message.at);
                                    const showSender = newDay || previous.sender.id !== message.sender.id;

                                    return (
                                        <Fragment key={message.id}>
                                            {newDay && (
                                                <li className="my-4 list-none text-center font-mono text-[11px] uppercase tracking-[0.18em] text-console-dim">{dayLabel(message.at)}</li>
                                            )}
                                            <MessageRow message={message} showSender={showSender} />
                                        </Fragment>
                                    );
                                })}
                            </ul>
                        )}
                    </div>

                    <form onSubmit={send} className="mt-4">
                        <div className="flex items-end gap-3">
                            <label htmlFor="body" className="sr-only">
                                Message
                            </label>
                            <textarea
                                id="body"
                                rows={2}
                                value={data.body}
                                maxLength={2000}
                                onChange={(e) => setData('body', e.target.value)}
                                onKeyDown={onKeyDown}
                                placeholder="Write a message… (Enter to send, Shift+Enter for a new line)"
                                className="block w-full resize-none border-console-line bg-console-panel text-sm text-console-text placeholder:text-console-dim focus:border-arka-teal focus:ring-arka-teal"
                            />
                            <ConsoleButton type="submit" disabled={processing || data.body.trim() === ''} className="flex items-center gap-2 py-2.5">
                                <SendIcon className="h-4 w-4" />
                                Send
                            </ConsoleButton>
                        </div>
                        {errors.body && <p className="mt-1.5 text-xs text-console-error">{errors.body}</p>}
                    </form>
                </Panel>
            </div>
        </AppLayout>
    );
}
