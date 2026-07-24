<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'github' => [
        // Contribution webhook — HMAC secret + scope. Contributors are tracked
        // directly from the webhook payload (no site accounts).
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
        'org' => env('GITHUB_ORG', 'Particle-Academy'),

        // Optional allowlist of "owner/repo" full names to reward. When set, only
        // these repos count (register the webhook per-repo anyway). Empty = any
        // repo in `org`. Comma-separated, e.g. "Particle-Academy/prism".
        'repos' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GITHUB_REPOS', ''))
        ))),
    ],

];
