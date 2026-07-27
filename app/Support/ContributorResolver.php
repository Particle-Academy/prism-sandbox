<?php

namespace App\Support;

use App\Models\Contributor;

/**
 * Resolves a GitHub user object (from a webhook payload or the REST API) to a
 * trackable {@see Contributor}. Shared by the webhook and the backfill command
 * so the "who earns XP" rules — skip bots, skip ignored/maintainer logins —
 * live in exactly one place and can never drift between the two ingestion paths.
 */
class ContributorResolver
{
    /**
     * Resolve (creating on first sight) a Contributor. Returns null for bots,
     * ignored logins, or malformed user objects.
     */
    public function resolve(mixed $githubUser): ?Contributor
    {
        if (! $this->isEligible($githubUser)) {
            return null;
        }

        return Contributor::updateOrCreate(
            ['github_id' => (string) data_get($githubUser, 'id')],
            [
                'github_username' => data_get($githubUser, 'login'),
                'avatar_url' => data_get($githubUser, 'avatar_url'),
            ],
        );
    }

    /**
     * Whether a GitHub user is eligible to earn XP — WITHOUT persisting anything.
     * Used by dry-run tooling that must not create contributor rows.
     */
    public function isEligible(mixed $githubUser): bool
    {
        $id = data_get($githubUser, 'id');
        $login = data_get($githubUser, 'login');

        if ($id === null || ! is_string($login) || $login === '' || data_get($githubUser, 'type') === 'Bot') {
            return false;
        }

        $ignored = array_map('strtolower', (array) config('gamification.ignored_users', []));

        return ! in_array(strtolower($login), $ignored, true);
    }
}
