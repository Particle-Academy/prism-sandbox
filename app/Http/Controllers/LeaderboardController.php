<?php

namespace App\Http\Controllers;

use App\Docs\DocsRepository;
use App\Models\Contributor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use LaravelFunLab\Facades\LFL;
use LaravelFunLab\Models\GamedMetric;
use LaravelFunLab\Models\Profile;

class LeaderboardController extends Controller
{
    /**
     * Profiles pulled before weighting. Everyone with points is shown — the
     * board credits every contributor, upstream's 140+ included — so this is
     * a safety bound, not a page size. Raw XP and weighted score rank
     * differently, so it must stay well above the number of contributors or
     * someone whose score sits in heavily weighted silos is cut before the
     * sort that would have promoted them.
     */
    private const POOL = 5000;

    public function __invoke(DocsRepository $docs): Response
    {
        /** @var array<string, array{name: string, xp: int, weight: float}> $silos */
        $silos = config('gamification.metrics', []);

        /** @var list<string> $upstream */
        $upstream = (array) config('gamification.upstream', []);

        $profiles = LFL::leaderboard()
            ->for(Contributor::class)
            ->by('xp')
            ->excludeOptedOut()
            ->take(self::POOL);

        // Per-silo XP for every profile in two queries. Profile::getXpFor()
        // costs two queries per call, which at a few hundred contributors and
        // seven silos was thousands per page view.
        $profiles->load('awardable', 'metrics');
        $slugs = GamedMetric::query()->pluck('slug', 'id');

        $upstreamXp = $this->upstreamXp($upstream);
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
            ->map(function (Profile $profile) use ($silos, $slugs, $upstreamXp): array {
                $xp = $this->siloXp($profile, $slugs);

                return [
                    'profile' => $profile,
                    'xp' => $xp,
                    'score' => $this->weightedScore($xp, $silos),
                    'upstream' => $this->weightedScore($upstreamXp[$profile->awardable->getKey()] ?? [], $silos),
                ];
            })
            ->sortByDesc(fn (array $entry): array => [
                $entry['score'],
                (int) $entry['profile']->total_xp,
            ])
            ->values()
            ->map(fn (array $entry, int $index): array => $this->row($entry, $silos, $index + 1))
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
            'upstream' => $upstream,
            'rows' => $rows,
        ]);
    }

    /**
     * @param  array{profile: Profile, xp: array<string, int>, score: float, upstream: float}  $entry
     * @param  array<string, array{name: string, xp: int, weight: float}>  $silos
     * @return array<string, mixed>
     */
    protected function row(array $entry, array $silos, int $rank): array
    {
        $profile = $entry['profile'];

        /** @var Contributor $contributor */
        $contributor = $profile->awardable;

        return [
            'rank' => $rank,
            'github_username' => $contributor->github_username,
            'avatar_url' => $contributor->avatar_url,
            'score' => round($entry['score'], 1),
            // The share of the score earned in the upstream repositories.
            'upstream' => round($entry['upstream'], 1),
            'total_xp' => (int) $profile->total_xp,
            'achievements' => (int) $profile->achievement_count,
            'silos' => collect($silos)->map(fn (array $meta, string $slug): array => [
                'slug' => $slug,
                'xp' => $entry['xp'][$slug] ?? 0,
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, string>  $slugs  gamed_metric_id => slug
     * @return array<string, int> slug => XP
     */
    protected function siloXp(Profile $profile, Collection $slugs): array
    {
        $xp = [];

        foreach ($profile->metrics as $metric) {
            $slug = $slugs[$metric->gamed_metric_id] ?? null;

            if ($slug !== null) {
                $xp[$slug] = (int) $metric->total_xp;
            }
        }

        return $xp;
    }

    /**
     * XP earned in the upstream repositories, per contributor and silo. Read
     * from the award claims, which record where each award was earned.
     *
     * @param  list<string>  $upstream
     * @return array<int, array<string, int>> contributor id => slug => XP
     */
    protected function upstreamXp(array $upstream): array
    {
        if ($upstream === []) {
            return [];
        }

        $xp = [];

        DB::table('xp_award_claims')
            ->whereIn('repo', array_map('strtolower', $upstream))
            ->groupBy('contributor_id', 'metric')
            ->select('contributor_id', 'metric', DB::raw('sum(xp) as xp'))
            ->get()
            ->each(function (object $claim) use (&$xp): void {
                $xp[(int) $claim->contributor_id][(string) $claim->metric] = (int) $claim->xp;
            });

        return $xp;
    }

    /**
     * Sum of each silo's XP scaled by its configured weight — the
     * `contributions` group score the board ranks on.
     *
     * @param  array<string, int>  $xp  slug => XP
     * @param  array<string, array{name: string, xp: int, weight: float}>  $silos
     */
    protected function weightedScore(array $xp, array $silos): float
    {
        $score = 0.0;

        foreach ($silos as $slug => $meta) {
            $score += ($xp[$slug] ?? 0) * (float) ($meta['weight'] ?? 1.0);
        }

        return $score;
    }
}
