import '../css/app.css';
import './bootstrap';

import { appearanceFor, applyAppearance } from '@/lib/appearance';
import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Keep the saved appearance in sync on every visit (e.g. after signing in or changing it in Profile).
router.on('navigate', (event) => applyAppearance(appearanceFor(event.detail.page)));

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        applyAppearance(appearanceFor(props.initialPage));

        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#2F8299',
    },
});
