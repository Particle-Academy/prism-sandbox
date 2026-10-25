<?php

namespace App\Http\Middleware;

use FancySeo\FancySeo;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            // The same resolved head the server rendered, so in-app navigation
            // updates it from one definition (SeoServiceProvider).
            'seo' => fn () => app(FancySeo::class)->forRequest($request)->toArray(),
            // Gates the nav link. A prominent link to a page that says
            // "not configured" is worse than no link at all.
            'demoEnabled' => filled(config('prism.providers.groq.api_key')),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            // Fortify sets a session `status` after actions like the password-reset email.
            'status' => fn () => $request->session()->get('status'),
        ];
    }
}
