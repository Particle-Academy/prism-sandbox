<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contribution XP silos
    |--------------------------------------------------------------------------
    |
    | Each community contribution type is its own fun-lab GamedMetric (silo).
    | `xp` is awarded per event; `weight` is the metric's contribution to the
    | overall weighted `contributions` group used for the leaderboard. Values
    | increase with the significance/effort of the contribution.
    |
    */

    'metrics' => [
        'star-xp' => [
            'name' => 'Stars',
            'xp' => (int) env('XP_STAR', 1),
            'weight' => 0.5,
        ],
        'pr-comment-xp' => [
            'name' => 'PR Comments',
            'xp' => (int) env('XP_PR_COMMENT', 2),
            'weight' => 1.0,
        ],
        'issue-xp' => [
            'name' => 'Issues Filed',
            'xp' => (int) env('XP_ISSUE', 5),
            'weight' => 2.0,
        ],
        'pr-review-xp' => [
            'name' => 'PR Reviews',
            'xp' => (int) env('XP_PR_REVIEW', 8),
            'weight' => 3.0,
        ],
        'pr-submitted-xp' => [
            'name' => 'Pull Requests Opened',
            'xp' => (int) env('XP_PR_SUBMITTED', 10),
            'weight' => 3.0,
        ],
        'pr-accepted-xp' => [
            'name' => 'Pull Requests Merged',
            'xp' => (int) env('XP_PR_ACCEPTED', 25),
            'weight' => 5.0,
        ],
    ],

    // The weighted metric-level-group that rolls the silos into one score.
    'group' => 'contributions',

    // How long a single GitHub object's award is de-duplicated (seconds).
    // One year — a redelivered/replayed webhook can never double-count.
    'dedup_seconds' => (int) env('XP_DEDUP_SECONDS', 31_536_000),

];
