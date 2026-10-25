import { createInertiaApp } from '@inertiajs/react';
import { setupFancyApp } from '@particle-academy/fancy-inertia';
import { Toast } from '@particle-academy/react-fancy';
import type { ComponentType, ReactNode } from 'react';
import { withPageSeo } from './components/page-seo';
import '@particle-academy/react-fancy/styles.css';

/**
 * App-wide providers mounted above the Inertia outlet — they survive page
 * swaps (toasts, etc. don't reset on navigation). Add `FancyDataRoot` from
 * `@particle-academy/fancy-query` here once you wire Laravel Echo for
 * realtime server-state invalidation. These sit OUTSIDE Inertia's context:
 * nothing here may call usePage().
 */
const providers = (outlet: ReactNode): ReactNode => (
    <Toast.Provider position="bottom-right">{outlet}</Toast.Provider>
);

createInertiaApp({
    // Titles arrive complete from the server (SeoServiceProvider); nothing is appended here.
    title: (title) => title,
    resolve: (name) => {
        // Typing the glob is what keeps this assignable to Inertia's
        // ComponentResolver — an untyped glob resolves to `unknown`.
        const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');
        const page = pages[`./pages/${name}.tsx`];
        if (!page) {
            return Promise.reject(new Error(`Inertia page not found: ${name}`));
        }
        // The head updater rides in each page's layout, inside Inertia's tree.
        return page().then((module) => withPageSeo(module.default));
    },
    // setupFancyApp builds the page-transition + provider tree and auto-detects
    // hydrateRoot vs createRoot. `appRoot: false` keeps the root lean (just our
    // providers); set it to `{}` to mount the full FancyAppRoot (Toast +
    // ScreenSystem + ECharts registration) instead.
    setup: ({ App, props, el }) => {
        // Inertia types `el` as nullable; fail loudly rather than cast, since a
        // missing root means the Blade shell is wrong and nothing will mount.
        if (!el) {
            throw new Error('Inertia root element not found');
        }

        return setupFancyApp({ el, App, props, providers, appRoot: false });
    },
});
