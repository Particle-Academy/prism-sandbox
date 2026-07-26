<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'Fancy') }}</title>

    {{-- Apply the saved/system theme before first paint to avoid a flash. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('fancy.theme');
                var dark = t ? t === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (dark) document.documentElement.classList.add('dark');
            } catch (e) { /* noop */ }
        })();
    </script>

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    @inertia

    {{-- Fancy Pixel: production-only visitor heuristics badge (Particle Academy). --}}
    @production
        @verbatim
        <script src="https://unpkg.com/@particle-academy/fancy-pixel/dist/fancy-pixel.global.min.js" data-site="gegxb2ehq3vl" data-style="badge" data-mode="floating" data-endpoint="https://ui.particle.academy/heuristics"></script>
        @endverbatim
    @endproduction
</body>
</html>
