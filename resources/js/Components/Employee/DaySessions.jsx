import FixList from '@/Components/Attendance/FixList';
import Collapsible from '@/Components/Console/Collapsible';
import { Eyebrow } from '@/Components/Console/Panel';
import StatusBadge, { Tag } from '@/Components/Console/StatusBadge';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import { fullDate, hms, parseDate } from '@/lib/format';

/**
 * One day of tracked time (▸ Sep 19, 2026): opens to its sessions and, if anything was fixed
 * that day, the time it replaced and the new time.
 */
export default function DaySessions({ date, sessions, fixes = [], correctionPending = false, defaultOpen = false }) {
    const weekday = parseDate(date).toLocaleDateString('en-US', { weekday: 'short' });
    const worked = sessions.reduce((total, session) => total + session.workedSeconds, 0);

    return (
        <Collapsible
            defaultOpen={defaultOpen}
            summary={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="font-medium text-console-heading">
                        {fullDate(date)} <span className="font-normal text-console-dim">· {weekday}</span>
                    </p>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-mono text-xs text-console-muted">
                            {sessions.length} {sessions.length === 1 ? 'session' : 'sessions'} · {hms(worked)}
                        </span>
                        {fixes.length > 0 && <Tag tone="live">Fixed</Tag>}
                        {correctionPending && <StatusBadge status="correction_pending" label="Correction pending" />}
                    </div>
                </div>
            }
        >
            <Table columns={['Client', 'Start', 'End', 'Break used', 'Total', 'Status']} actions={false} isEmpty={sessions.length === 0} emptyMessage="No sessions." minWidth={640}>
                {sessions.map((session) => (
                    <Row key={session.id}>
                        <Cell className="font-medium text-console-heading">{session.client}</Cell>
                        <Cell className="font-mono">{session.start}</Cell>
                        <Cell className="font-mono">{session.end ?? '—'}</Cell>
                        <Cell className="font-mono">
                            {session.breakUsed} / {session.breakAllowance} min
                        </Cell>
                        <Cell className="font-mono">{hms(session.workedSeconds)}</Cell>
                        <Cell>
                            <div className="flex flex-wrap gap-1.5">
                                <StatusBadge status={session.status} label={session.statusLabel} />
                                {session.paidOut && <Tag tone="closed">In released payroll</Tag>}
                            </div>
                        </Cell>
                    </Row>
                ))}
            </Table>

            {fixes.length > 0 && (
                <div className="mt-5">
                    <Eyebrow>What changed on this day</Eyebrow>
                    <div className="mt-2">
                        <FixList fixes={fixes} showDate={false} />
                    </div>
                </div>
            )}
        </Collapsible>
    );
}
