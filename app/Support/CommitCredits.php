<?php

namespace App\Support;

use App\Models\Contributor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Credits a piece of work once, however many repositories it lands in.
 *
 * Commit XP is de-duplicated by SHA, but the same work reaches us under more
 * than one SHA: a pull request opened upstream is absorbed into the fork by
 * cherry-pick (new SHA), and upstream squash-merges its pull requests (new SHA
 * again). What survives both is the author and the AUTHORED date of the
 * original commits — git keeps them through a cherry-pick, and a squash commit
 * can be traced back to its pull request's commits.
 *
 * So every credited commit records those identities, and a commit whose
 * identity is already credited to the same contributor under another SHA is
 * not credited again.
 */
class CommitCredits
{
    /**
     * Identities for a commit authored at the given dates.
     *
     * @param  iterable<mixed>  $authoredAt
     * @return list<string>
     */
    public static function identities(iterable $authoredAt): array
    {
        $identities = [];

        foreach ($authoredAt as $date) {
            if (! is_string($date) || $date === '') {
                continue;
            }

            try {
                $identities[] = 'authored:'.CarbonImmutable::parse($date)->utc()->format('Y-m-d\TH:i:s\Z');
            } catch (Throwable) {
                // An unparseable date identifies nothing.
            }
        }

        return array_values(array_unique($identities));
    }

    /**
     * Whether this work is already credited to the contributor under a
     * different commit.
     *
     * @param  list<string>  $identities
     */
    public static function creditedElsewhere(Contributor $contributor, array $identities, string $eventKey): bool
    {
        if ($identities === []) {
            return false;
        }

        return DB::table('commit_credits')
            ->where('contributor_id', $contributor->getKey())
            ->whereIn('identity_hash', array_map(fn (string $identity): string => hash('sha256', $identity), $identities))
            ->where('event_key', '!=', $eventKey)
            ->exists();
    }

    /**
     * Record the identities of a commit that holds the credit. Called for
     * duplicates too, so commits credited before identities were recorded
     * claim theirs the next time the backfill walks past them.
     *
     * @param  list<string>  $identities
     */
    public static function remember(Contributor $contributor, array $identities, string $eventKey): void
    {
        $rows = array_map(fn (string $identity): array => [
            'contributor_id' => $contributor->getKey(),
            'identity_hash' => hash('sha256', $identity),
            'identity' => $identity,
            'event_key' => $eventKey,
            'created_at' => now(),
            'updated_at' => now(),
        ], $identities);

        if ($rows !== []) {
            DB::table('commit_credits')->insertOrIgnore($rows);
        }
    }
}
