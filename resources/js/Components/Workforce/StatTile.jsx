/** Clickable count tile that doubles as a filter. */
export default function StatTile({ label, value, active = false, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`border px-5 py-4 text-left transition-colors ${
                active ? "border-arka-teal bg-arka-teal/5" : "border-console-line hover:border-arka-teal hover:bg-console-raised"
            }`}
        >
            <p className="text-[11px] uppercase tracking-[0.2em] text-console-muted">{label}</p>
            <p className="mt-2 font-mono text-3xl font-medium text-console-heading">{value}</p>
        </button>
    );
}
