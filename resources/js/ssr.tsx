import { createFancyServer } from '@particle-academy/fancy-inertia/server';
import { Toast } from '@particle-academy/react-fancy';
import type { ComponentType, ReactNode } from 'react';
import { withPageSeo } from './components/page-seo';

/**
 * Server-side rendering entry. It renders each page inside the SAME Fancy
 * provider tree as the client entry (resources/js/app.tsx) so the markup
 * hydrates cleanly — keep this `providers` tree identical to app.tsx's.
 *
 * Build it with `npm run build` (which runs `vite build --ssr`), then run the
 * SSR daemon with `php artisan inertia:start-ssr`. SSR is OFF by default — set
 * `INERTIA_SSR_ENABLED=true` in `.env` to use it.
 */
const providers = (outlet: ReactNode): ReactNode => (
    <Toast.Provider position="bottom-right">{outlet}</Toast.Provider>
);

createFancyServer({
    resolve: (name) => {
        const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx', { eager: true });
        const module = pages[`./pages/${name}.tsx`];
        // Same as app.tsx: the head updater rides in the page's layout, inside Inertia's tree.
        if (module) {
            withPageSeo(module.default);
        }
        return module;
    },
    providers,
    appRoot: false,
});
