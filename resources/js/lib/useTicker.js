import { useEffect, useState } from 'react';

/**
 * Seconds elapsed since the page's data was loaded, ticking every second while `active`.
 * Timers add this to the server's worked seconds, so client clock skew never matters.
 */
export default function useTicker(active, resetKey) {
    const [elapsed, setElapsed] = useState(0);

    useEffect(() => {
        setElapsed(0);

        if (!active) return undefined;

        const startedAt = Date.now();
        const timer = setInterval(() => setElapsed(Math.floor((Date.now() - startedAt) / 1000)), 1000);

        return () => clearInterval(timer);
    }, [active, resetKey]);

    return elapsed;
}
