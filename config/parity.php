<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Upstream (prism-php/prism) for the public parity dashboard
    |--------------------------------------------------------------------------
    |
    | Our fork against the project it began as, and the pull requests we have
    | opened there. Read by `parity:snapshot` over the public GitHub API with no
    | token. The same values the Lab's /lab/drift tracker uses.
    |
    */

    'upstream' => [
        'repo' => 'prism-php/prism',
        'fork' => 'Particle-Academy/prism',
        'branch' => 'main',
        // Our contributions upstream: PRs opened in `repo` by these GitHub logins.
        'pr_authors' => ['wishborn'],
        // An open PR with no merge after this many days is marked stale.
        'pr_stale_days' => 30,
        // Open PRs whose review and CI state are read (three API calls each).
        // Above this the rest show "unknown" rather than spend the rate limit.
        'pr_detail_limit' => 12,
    ],

];
