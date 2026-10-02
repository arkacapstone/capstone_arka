/**
 * Applies the Light / Dark / System appearance to <html>. The saved choice comes from the
 * server (auth.user.appearance), so it follows the user to every device; guest screens such as
 * Login always stay light. "System" follows the device setting live.
 */
const media = typeof window !== 'undefined' ? window.matchMedia('(prefers-color-scheme: dark)') : null;
let current = 'light';

function paint() {
    const dark = current === 'dark' || (current === 'system' && media?.matches);

    document.documentElement.classList.toggle('dark', Boolean(dark));
    document.documentElement.dataset.appearance = current;
}

media?.addEventListener('change', () => current === 'system' && paint());

export function applyAppearance(mode) {
    current = ['light', 'dark', 'system'].includes(mode) ? mode : 'light';
    paint();
}

export function appearanceFor(page) {
    return page?.props?.auth?.user?.appearance ?? 'light';
}
