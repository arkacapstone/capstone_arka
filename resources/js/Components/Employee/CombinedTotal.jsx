import { hms } from '@/lib/format';
import useTicker from '@/lib/useTicker';

/** Combined running total across every client timer, ticking live while any timer runs. */
export default function CombinedTotal({ board }) {
    const runningNow = board.clients.filter((client) => client.timer?.status === 'running').length;
    const elapsed = useTicker(runningNow > 0, board.serverNow);

    return <>{hms(board.combinedSeconds + elapsed * runningNow)}</>;
}
