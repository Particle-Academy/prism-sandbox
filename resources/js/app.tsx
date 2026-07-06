import { createInertiaApp } from '@inertiajs/react';
import { setupFancyApp } from '@particle-academy/fancy-inertia';
import { Toast } from '@particle-academy/react-fancy';
import type { ReactNode } from 'react';
import '@particle-academy/react-fancy/styles.css';

const appName = import.meta.env.VITE_APP_NAME ?? 'Fancy';

/**
 * App-wide providers mounted above the Inertia outlet — they survive page
 * swaps (toasts, etc. don't reset on navigation). Add `FancyDataRoot` from
 * `@particle-academy/fancy-query` here once you wire Laravel Echo for
 * realtime server-state invalidation.
 */
const providers = (outlet: ReactNode): ReactNode => (
    <Toast.Provider position="bottom-right">{outlet}</Toast.Provider>
);

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.tsx');
        const page = pages[`./Pages/${name}.tsx`];
        if (!page) {
            return Promise.reject(new Error(`Inertia page not found: ${name}`));
        }
        return page().then((module: { default: unknown }) => module.default);
    },
    // setupFancyApp builds the page-transition + provider tree and auto-detects
    // hydrateRoot vs createRoot. `appRoot: false` keeps the root lean (just our
    // providers); set it to `{}` to mount the full FancyAppRoot (Toast +
    // ScreenSystem + ECharts registration) instead.
    setup: ({ App, props, el }) => setupFancyApp({ el, App, props, providers, appRoot: false }),
});
