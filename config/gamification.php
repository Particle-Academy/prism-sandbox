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
        // Commit authorship, counted only for commits that actually landed in
        // THIS fork. Most community work reaches us by absorption — the PR is
        // opened upstream and we replay the commit — so the author never fires
        // a pull_request event here and would otherwise score nothing at all
        // while their code ships in every release.
        // Ordering against merged PRs is guaranteed by the weighted group, not
        // by keeping this number small, so it can be generous enough to credit
        // absorbed work visibly: 10 XP at weight 1.0 is 10 points against 125
        // for a merged PR, so it still takes ~12 landed commits to match one.
        'commit-xp' => [
            'name' => 'Commits Landed',
            'xp' => (int) env('XP_COMMIT', 10),
            'weight' => 1.0,
        ],
    ],

    // The weighted metric-level-group that rolls the silos into one score.
    'group' => 'contributions',

    // PROVISIONAL enforcement defaults, awaiting the owner's scoring-policy ruling.
    // Counts awarded events, not XP. Daily caps use the event's UTC date when
    // assigned by GitHub; commits use the processing day because git dates are
    // author-controlled. PR caps combine comments/reviews across contributors
    // over the PR's lifetime; opening/merge credit is outside that PR bucket.
    // Zero stops awards in that scope; it does not disable enforcement.
    'limits' => [
        'per_contributor_day' => (int) env('XP_EVENTS_PER_CONTRIBUTOR_DAY', 20),
        'per_pr' => (int) env('XP_INTERACTIONS_PER_PR', 40),
        'pr_metrics' => ['pr-comment-xp', 'pr-review-xp'],
    ],

    // How long a single GitHub object's award is de-duplicated (seconds).
    // One year — a redelivered/replayed webhook can never double-count.
    'dedup_seconds' => (int) env('XP_DEDUP_SECONDS', 31_536_000),

    // Upstream repositories whose contributions are tracked alongside ours, so
    // the board credits the people who built Prism before the fork, and shows
    // which of anyone's points were earned there. We cannot install a webhook
    // on a repo we do not own, so these are reconciled by the backfill only.
    // Stars are not counted upstream: thousands of stargazers would bury the
    // people who contributed, and a star is not a contribution.
    'upstream' => array_values(array_filter(array_map(
        fn (string $repo): string => strtolower(trim($repo)),
        explode(',', (string) env('GITHUB_UPSTREAM_REPOS', 'prism-php/prism'))
    ))),

    // GitHub logins (case-insensitive) excluded from XP and the leaderboard.
    // Maintainers are NOT excluded: the board credits everyone who builds Prism.
    // `invalid-email-address` is the placeholder account GitHub attributes
    // commits with unrecognised author emails to; it is not a person. Add more
    // via the comma-separated GITHUB_IGNORED_USERS env var.
    'ignored_users' => array_values(array_unique(array_merge(
        ['invalid-email-address'],
        array_filter(array_map('trim', explode(',', (string) env('GITHUB_IGNORED_USERS', ''))))
    ))),

];
