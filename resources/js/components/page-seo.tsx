import { usePage } from '@inertiajs/react';
import { Seo } from '@particle-academy/fancy-inertia/seo';

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
            jsonLd={seo.jsonLd}
            clientOnly
        />
    );
}
