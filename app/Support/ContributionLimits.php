<?php

namespace App\Support;

use App\Models\Contributor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ContributionLimits
{
    /** Canonical context shared by webhook and historical reconciliation. */
    public static function context(string $repo, mixed $occurredAt = null, mixed $prNumber = null, mixed $prUrl = null): array
    {
        $day = (is_string($occurredAt) && $occurredAt !== '' ? CarbonImmutable::parse($occurredAt) : CarbonImmutable::now())->utc()->toDateString();
        $number = is_scalar($prNumber) ? (string) $prNumber : '';
        if ((! ctype_digit($number) || trim($number, '0') === '') && is_string($prUrl)) {
            preg_match('~/pulls?/(\d+)(?:[/?#]|$)~', $prUrl, $match);
            $number = $match[1] ?? '';
        }
        // Missing PR identity joins a restrictive shared bucket rather than
        // letting a malformed record bypass the per-PR cap.
        $number = ctype_digit($number) && trim($number, '0') !== '' ? ltrim($number, '0') : 'unknown';

        return ['day' => $day, 'pr_key' => strtolower($repo).':'.$number];
    }

    /** Called inside XpAwarder's transaction while the contributor row is locked. */
    public static function allow(Contributor $contributor, string $metric, string $eventKey, ?array $context): bool
    {
        $context ??= self::context('unknown');
        $eventHash = hash('sha256', $eventKey);
        $decisionQuery = DB::table('contribution_limit_decisions')
            ->where('contributor_id', $contributor->id)->where('metric', $metric)->where('event_hash', $eventHash);
        $previous = $decisionQuery->first();
        if ($previous !== null) {
            if ($previous->allowed) {
                throw new RuntimeException('Accepted quota decision lacks a matching durable XP award');
            }

            return false;
        }

        $scopes = [['subject' => 'day:'.$contributor->id.':'.$context['day'],
            'limit' => max(0, (int) config('gamification.limits.per_contributor_day', 20))]];
        if (in_array($metric, (array) config('gamification.limits.pr_metrics', []), true)) {
            $scopes[] = ['subject' => 'pr:'.$context['pr_key'],
                'limit' => max(0, (int) config('gamification.limits.per_pr', 40))];
        }

        $allowed = true;
        foreach ($scopes as &$scope) {
            $scope['hash'] = hash('sha256', $scope['subject']);
            // Upsert acquires/creates the bucket atomically without resetting
            // its counter. PR bucket locking serializes different contributors.
            DB::table('contribution_limit_buckets')->upsert([
                'bucket_hash' => $scope['hash'], 'used' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ], ['bucket_hash'], ['bucket_hash']);
            $bucket = DB::table('contribution_limit_buckets')->where('bucket_hash', $scope['hash'])->lockForUpdate()->first();
            $allowed = $allowed && $bucket->used < $scope['limit'];
        }
        unset($scope);

        DB::table('contribution_limit_decisions')->insert([
            'contributor_id' => $contributor->id, 'metric' => $metric, 'event_hash' => $eventHash,
            'event_day' => $context['day'], 'allowed' => $allowed,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($allowed) {
            foreach ($scopes as $scope) {
                DB::table('contribution_limit_buckets')->where('bucket_hash', $scope['hash'])->increment('used');
            }
        }

        return $allowed;
    }
}
