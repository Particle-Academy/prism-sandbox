<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use LaravelFunLab\Facades\LFL;

/**
 * Seeds the community-contribution gamification taxonomy: one XP silo
 * (GamedMetric) per contribution type, a level curve per silo, the weighted
 * `contributions` rollup group, and the contribution achievements.
 *
 * Every LFL::setup() call is an idempotent upsert-by-slug, so this is safe to
 * re-run (it is invoked from a migration so it deploys where db:seed does not).
 */
class FunLabSeeder extends Seeder
{
    /** @var array<int, array{xp: int, name: string}> */
    protected array $levelCurve = [
        1 => ['xp' => 0, 'name' => 'Newcomer'],
        2 => ['xp' => 50, 'name' => 'Contributor'],
        3 => ['xp' => 200, 'name' => 'Regular'],
        4 => ['xp' => 500, 'name' => 'Core'],
        5 => ['xp' => 1000, 'name' => 'Legend'],
    ];

    /** @var array<int, array{xp: int, name: string}> */
    protected array $groupCurve = [
        1 => ['xp' => 0, 'name' => 'Newcomer'],
        2 => ['xp' => 100, 'name' => 'Contributor'],
        3 => ['xp' => 400, 'name' => 'Regular'],
        4 => ['xp' => 1000, 'name' => 'Core'],
        5 => ['xp' => 2500, 'name' => 'Legend'],
    ];

    public function run(): void
    {
        /** @var array<string, array{name: string, xp: int, weight: float}> $metrics */
        $metrics = config('gamification.metrics', []);
        $group = (string) config('gamification.group', 'contributions');

        // 1. One XP silo per contribution type, each with a level curve.
        foreach ($metrics as $slug => $meta) {
            LFL::setup('gamed-metric', [
                'slug' => $slug,
                'name' => $meta['name'],
                'active' => true,
            ]);

            foreach ($this->levelCurve as $level => $tier) {
                LFL::setup('metric-level', [
                    'metric' => $slug,
                    'level' => $level,
                    'xp' => $tier['xp'],
                    'name' => $tier['name'],
                ]);
            }
        }

        // 2. Weighted rollup group that combines the silos into one score.
        LFL::setup('metric-level-group', [
            'slug' => $group,
            'name' => 'Contributions',
        ]);

        foreach ($metrics as $slug => $meta) {
            LFL::setup('metric-level-group-metric', [
                'group' => $group,
                'metric' => $slug,
                'weight' => $meta['weight'],
            ]);
        }

        foreach ($this->groupCurve as $level => $tier) {
            LFL::setup('metric-level-group-level', [
                'group' => $group,
                'level' => $level,
                'xp' => $tier['xp'],
                'name' => $tier['name'],
            ]);
        }

        // 3. Contribution achievements.
        LFL::setup('achievement', [
            'slug' => 'first-contribution',
            'name' => 'First Contribution',
            'description' => 'Made your first tracked contribution to the project.',
            'icon' => 'sparkles',
        ]);

        LFL::setup('achievement', [
            'slug' => 'first-pr',
            'name' => 'First Merged PR',
            'description' => 'Had your first pull request merged.',
            'icon' => 'git-pull-request',
        ]);
    }
}
