import { usePage } from '@inertiajs/react';
import { Seo } from '@particle-academy/fancy-inertia/seo';
import type { ComponentType, ReactNode } from 'react';

/** The head the server resolved for this page (fancy-seo's SeoData::toArray()). */
type SharedSeo = {
    title: string;
    description: string;
    canonical: string;
    image: string | null;
    type: string;
    siteName: string;
    locale: string;
    robots: string;
    jsonLd: object[];
    twitterCard: 'summary' | 'summary_large_image';
    twitterSite: string | null;
};

/**
 * Keeps the document head in step with in-app navigation. The first byte is
 * rendered on the server by `<x-fancy-seo::head />` (SeoServiceProvider); this
 * only takes over after hydration, so it is client-only and never duplicates
 * the server's tags. Pages carry no SEO of their own: there is one definition.
 */
export function PageSeo() {
    const seo = usePage<{ seo?: SharedSeo }>().props.seo;

    if (!seo) {
        return null;
    }

    return (
        <Seo
            title={seo.title}
            description={seo.description || undefined}
            canonical={seo.canonical}
            image={seo.image ?? undefined}
            type={seo.type}
            siteName={seo.siteName}
            locale={seo.locale}
            noindex={seo.robots.includes('noindex')}
            twitterCard={seo.twitterCard}
            twitterSite={seo.twitterSite ?? undefined}
            // No jsonLd here: the server already rendered it on the first byte, and its
            // <script> tags carry no head-key, so a client copy would duplicate them.
            // Structured data matters to crawlers, which load every URL fresh.
            clientOnly
        />
    );
}

type PageComponent = ComponentType & { layout?: (page: ReactNode) => ReactNode };

/**
 * Mount PageSeo through the page's default layout. It must render INSIDE
 * Inertia's tree, because it reads `usePage()`: setupFancyApp's `providers`
 * wrap the outlet from outside that context, and mounting it there threw
 * "usePage must be used within the Inertia component", which left the page
 * blank (2026-10-25). A page that sets its own layout keeps it.
 */
export function withPageSeo<T extends PageComponent>(Page: T): T {
    Page.layout ??= (page: ReactNode) => (
        <>
            <PageSeo />
            {page}
        </>
    );

    return Page;
}
