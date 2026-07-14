<?php

namespace App\Http\Controllers;

use App\Docs\DocsRepository;
use App\Models\Contributor;
use Inertia\Inertia;
use Inertia\Response;
use LaravelFunLab\Facades\LFL;
use LaravelFunLab\Models\Profile;

class LeaderboardController extends Controller
{
    public function __invoke(DocsRepository $docs): Response
    {
        /** @var array<string, array{name: string, xp: int, weight: float}> $silos */
        $silos = config('gamification.metrics', []);

        $profiles = LFL::leaderboard()
            ->for(Contributor::class)
            ->by('xp')
            ->excludeOptedOut()
            ->take(50);

        $profiles->load('awardable');

        $ignored = array_map('strtolower', (array) config('gamification.ignored_users', []));

        $rows = $profiles
            ->filter(fn (Profile $profile): bool => $profile->awardable instanceof Contributor
                && (int) $profile->total_xp > 0
                && ! in_array(strtolower((string) $profile->awardable->github_username), $ignored, true))
            ->values()
            ->map(fn (Profile $profile, int $index): array => $this->row($profile, $silos, $index + 1))
            ->all();

        return Inertia::render('Leaderboard', [
            'version' => $docs->version(),
            'silos' => collect($silos)->map(fn (array $meta, string $slug): array => [
                'slug' => $slug,
                'name' => $meta['name'],
            ])->values()->all(),
            'rows' => $rows,
        ]);
    }

    /**
     * @param  array<string, array{name: string, xp: int, weight: float}>  $silos
     * @return array<string, mixed>
     */
    protected function row(Profile $profile, array $silos, int $rank): array
    {
        /** @var Contributor $contributor */
        $contributor = $profile->awardable;

        return [
            'rank' => $rank,
            'github_username' => $contributor->github_username,
            'avatar_url' => $contributor->avatar_url,
            'total_xp' => (int) $profile->total_xp,
            'achievements' => (int) $profile->achievement_count,
            'silos' => collect($silos)->map(fn (array $meta, string $slug): array => [
                'slug' => $slug,
                'xp' => $contributor->getXpFor($slug),
            ])->values()->all(),
        ];
    }
}
