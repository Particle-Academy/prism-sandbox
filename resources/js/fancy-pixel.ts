import { mountPixel } from '@particle-academy/fancy-pixel';

// This entry is included by Blade only in production. Preserve the existing
// badge and collection settings; the code now ships in our reviewed Vite build.
mountPixel({
    siteKey: 'gegxb2ehq3vl',
    style: 'badge',
    mode: 'floating',
    endpoint: 'https://ui.particle.academy/heuristics',
    collect: true,
});
