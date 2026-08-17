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
    /** Rows shown on the leaderboard. */
    private const SHOWN = 50;

    /**
     * Candidates pulled before weighting. Raw XP and weighted score rank
     * differently, so taking only SHOWN here would let a contributor whose
     * score is concentrated in heavily weighted silos be truncated away before
     * the sort that would have promoted them.
     */
    private const POOL = 500;

    public function __invoke(DocsRepository $docs): Response
    {
        /** @var array<string, array{name: string, xp: int, weight: float}> $silos */
        $silos = config('gamification.metrics', []);

        $profiles = LFL::leaderboard()
            ->for(Contributor::class)
            ->by('xp')
            ->excludeOptedOut()
            ->take(self::POOL);

        $profiles->load('awardable');

        $ignored = array_map('strtolower', (array) config('gamification.ignored_users', []));

        $rows = $profiles
            ->filter(fn (Profile $profile): bool => $profile->awardable instanceof Contributor
                && (int) $profile->total_xp > 0
                && ! in_array(strtolower((string) $profile->awardable->github_username), $ignored, true))
            // Rank on the weighted contributions group, not raw XP. Silos are
            // not equivalent: a merged pull request is worth 5x a landed commit
            // per point, so bulk activity in a light silo can no longer overtake
            // a heavier one just by volume. LFL's builder can only sort by
            // 'xp' | 'achievements' | 'prizes', so the weighting happens here.
            ->map(fn (Profile $profile): array => [
                'profile' => $profile,
                'score' => $this->weightedScore($profile->awardable, $silos),
            ])
            ->sortByDesc(fn (array $entry): array => [
                $entry['score'],
                (int) $entry['profile']->total_xp,
            ])
            ->values()
            ->take(self::SHOWN)
            ->map(fn (array $entry, int $index): array => $this->row(
                $entry['profile'],
                $silos,
                $index + 1,
                $entry['score'],
            ))
            ->all();

        return Inertia::render('Leaderboard', [
            'version' => $docs->version(),
            // xp/weight travel with each silo so the page can explain the
            // scoring from the same config the awards are made from, rather
            // than a hand-maintained copy that drifts the first time a value
            // changes.
            'silos' => collect($silos)->map(fn (array $meta, string $slug): array => [
                'slug' => $slug,
                'name' => $meta['name'],
                'xp' => (int) $meta['xp'],
                'weight' => (float) $meta['weight'],
            ])->values()->all(),
            'rows' => $rows,
        ]);
    }

    /**
     * @param  array<string, array{name: string, xp: int, weight: float}>  $silos
     * @return array<string, mixed>
     */
    protected function row(Profile $profile, array $silos, int $rank, float $score): array
    {
        /** @var Contributor $contributor */
        $contributor = $profile->awardable;

        return [
            'rank' => $rank,
            'github_username' => $contributor->github_username,
            'avatar_url' => $contributor->avatar_url,
            'score' => round($score, 1),
            'total_xp' => (int) $profile->total_xp,
            'achievements' => (int) $profile->achievement_count,
            'silos' => collect($silos)->map(fn (array $meta, string $slug): array => [
                'slug' => $slug,
                'xp' => $contributor->getXpFor($slug),
            ])->values()->all(),
        ];
    }

    /**
     * Sum of each silo's XP scaled by its configured weight — the
     * `contributions` group score the board ranks on.
     *
     * @param  array<string, array{name: string, xp: int, weight: float}>  $silos
     */
    protected function weightedScore(Contributor $contributor, array $silos): float
    {
        $score = 0.0;

        foreach ($silos as $slug => $meta) {
            $score += $contributor->getXpFor($slug) * (float) ($meta['weight'] ?? 1.0);
        }

        return $score;
    }
}
