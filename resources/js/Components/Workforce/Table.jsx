/** Console data table. `columns` are header labels; the last one is right-aligned when `actions` is set. */
export default function Table({ columns, actions = true, isEmpty, emptyMessage, minWidth = 760, children }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-[13px]" style={{ minWidth }}>
                <thead>
                    <tr className="border-y border-console-line text-[11px] uppercase tracking-[0.15em] text-console-muted">
                        {columns.map((column, index) => (
                            <th
                                key={column}
                                className={`py-2.5 font-medium ${actions && index === columns.length - 1 ? 'text-right' : 'pr-4'}`}
                            >
                                {column}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {isEmpty ? (
                        <tr>
                            <td colSpan={columns.length} className="py-10 text-center font-condensed text-[15px] italic text-console-muted">
                                {emptyMessage}
                            </td>
                        </tr>
                    ) : (
                        children
                    )}
                </tbody>
            </table>
        </div>
    );
}

export function Row({ children }) {
    return <tr className="border-b border-console-line transition-colors hover:bg-console-raised">{children}</tr>;
}

export function Cell({ className = '', children }) {
    return <td className={`py-3 pr-4 align-top ${className}`}>{children}</td>;
}
