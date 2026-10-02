import Table, { Cell, Row } from '@/Components/Workforce/Table';
import { dateTime, fullDate } from '@/lib/format';

/** Every fix on a contractor's attendance: the time it replaced, the new time, and why. */
export default function FixList({ fixes, showDate = true, emptyMessage = 'No fixes.' }) {
    const columns = [...(showDate ? ['Date'] : []), 'Client', 'Before', 'After', 'Reason', 'Fixed'];

    return (
        <Table columns={columns} actions={false} isEmpty={fixes.length === 0} emptyMessage={emptyMessage} minWidth={showDate ? 760 : 640}>
            {fixes.map((fix) => (
                <Row key={fix.id}>
                    {showDate && <Cell className="font-mono">{fullDate(fix.date)}</Cell>}
                    <Cell className="text-console-heading">
                        {fix.client ?? '—'}
                        <p className="text-xs text-console-dim">{fix.field}</p>
                    </Cell>
                    <Cell className="font-mono font-normal text-console-muted">{fix.before}</Cell>
                    <Cell className="font-mono font-medium text-console-heading">{fix.after}</Cell>
                    <Cell className="max-w-xs text-console-muted">{fix.reason}</Cell>
                    <Cell className="text-xs text-console-muted">
                        <span className="font-mono">{dateTime(fix.fixedAt)}</span>
                        <p className="text-console-dim">{fix.by}</p>
                    </Cell>
                </Row>
            ))}
        </Table>
    );
}
