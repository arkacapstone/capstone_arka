/**
 * Inline "done" confirmation: a dark card with a filled check, shown right where the action happened.
 */
export default function SuccessNotice({ children, className = '' }) {
    return (
        <div
            role="status"
            className={`inline-flex max-w-xl items-center gap-3 rounded-md border border-white/10 bg-[#0f0f10] px-5 py-3.5 text-sm font-semibold text-white shadow-lg shadow-black/20 ${className}`}
        >
            <svg viewBox="0 0 20 20" aria-hidden="true" className="h-5 w-5 shrink-0">
                <circle cx="10" cy="10" r="10" fill="currentColor" />
                <path d="M5.8 10.3 8.6 13l5.6-5.8" fill="none" stroke="#0f0f10" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
            <span>{children}</span>
        </div>
    );
}
