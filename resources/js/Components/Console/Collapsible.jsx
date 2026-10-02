import { ChevronRightIcon } from '@/Components/Icons';

/**
 * A row that opens to show its details (▸ closed, ▾ open). Built on <details>, so it works with
 * the keyboard and screen readers out of the box.
 */
export default function Collapsible({ summary, children, defaultOpen = false, className = '' }) {
    return (
        <details open={defaultOpen} className={`group border border-console-line bg-console-panel ${className}`}>
            <summary className="flex cursor-pointer list-none items-center gap-3 px-4 py-3 text-sm transition-colors hover:bg-console-raised [&::-webkit-details-marker]:hidden">
                <ChevronRightIcon className="h-4 w-4 shrink-0 text-console-muted transition-transform group-open:rotate-90" />
                <div className="min-w-0 flex-1">{summary}</div>
            </summary>
            <div className="border-t border-console-line px-4 py-4">{children}</div>
        </details>
    );
}
