const pesoFormatter = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
});

/** ₱20,000.00 */
export function peso(amount) {
    return pesoFormatter.format(Number(amount) || 0);
}

/** Parses a Y-m-d string as a local date (avoids UTC day shifts). */
export function parseDate(value) {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

/** "Sep 25" */
export function shortDate(value) {
    return parseDate(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

/** "Sep 11 – Sep 25, 2026" */
export function dateRange(start, end) {
    const year = parseDate(end).getFullYear();

    return `${shortDate(start)} – ${shortDate(end)}, ${year}`;
}

/** "In 5d", "Today", "2d ago" */
export function daysFromNow(days) {
    if (days === 0) return 'Today';

    return days > 0 ? `In ${days}d` : `${Math.abs(days)}d ago`;
}

/** "3m ago", "2h ago", "Sep 20" */
export function timeAgo(isoString) {
    if (!isoString) return '';

    const seconds = Math.round((Date.now() - new Date(isoString).getTime()) / 1000);

    if (seconds < 60) return 'just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`;

    return new Date(isoString).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

/** "Sep 25, 2026" */
export function fullDate(value) {
    return value ? parseDate(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
}

/** "Oct 1, 3:14 PM" */
export function dateTime(isoString) {
    if (!isoString) return '—';

    return new Date(isoString).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}

/** "Friday, 25 September 2026" */
export function longDate(value) {
    return parseDate(value).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}

/** "22:00" → "10:00 PM" */
export function clock(value) {
    if (!value) return '—';

    const [hours, minutes] = value.split(':').map(Number);
    const suffix = hours >= 12 ? 'PM' : 'AM';

    return `${hours % 12 || 12}:${String(minutes).padStart(2, '0')} ${suffix}`;
}

/** Local "YYYY-MM-DD" for today. */
export function todayIso() {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

/**
 * min/max for a birthday date input: ages 10 to 64, matching the server's AllowedAge rule.
 */
export function birthdayRange() {
    const iso = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const yearsAgo = (years) => {
        const date = new Date();
        date.setFullYear(date.getFullYear() - years);

        return date;
    };

    const oldest = yearsAgo(65);
    oldest.setDate(oldest.getDate() + 1);

    return { min: iso(oldest), max: iso(yearsAgo(10)) };
}

/** 1536000 → "1.5 MB" */
export function fileSize(bytes) {
    if (!bytes) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

/** Greeting for the time of day. */
export function greeting() {
    const hour = new Date().getHours();

    return hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
}

/** 11565 → "03:12:45" (timers, always two-digit hours). */
export function hms(totalSeconds) {
    const seconds = Math.max(0, Math.floor(totalSeconds || 0));
    const pad = (value) => String(value).padStart(2, '0');

    return `${pad(Math.floor(seconds / 3600))}:${pad(Math.floor((seconds % 3600) / 60))}:${pad(seconds % 60)}`;
}

/** 900 → "15h 00m" */
export function hoursMinutes(totalMinutes) {
    const minutes = Math.max(0, Math.round(totalMinutes || 0));

    return `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, '0')}m`;
}

/** "2026-09" → "September 2026" */
export function monthLabel(value) {
    const [year, month] = value.split('-').map(Number);

    return new Date(year, month - 1, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
}

/** "2026-09" shifted by ±n months. */
export function shiftMonth(value, delta) {
    const [year, month] = value.split('-').map(Number);
    const date = new Date(year, month - 1 + delta, 1);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}
