<?php

namespace App\Support;

use App\Models\Contributor;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LaravelFunLab\Facades\LFL;
use LaravelFunLab\Models\EventLog;
use RuntimeException;
use Throwable;

/**
 * A durable unique claim, XP and its audit event commit together. The contributor
 * row serializes awards across metrics; the unique index arbitrates duplicate
 * events even when two deliveries saw the same stale legacy dedup lookup.
 * Cache entries are disposable hints and never authorize or suppress an award.
 */
class XpAwarder
{
    public static function award(
        ?Contributor $contributor,
        string $metric,
        int $amount,
        string $reason,
        string $dedupKey,
        ?int $dedupSeconds = null,
        ?array $context = null,
    ): AwardOutcome {
        if (! $contributor instanceof Contributor || $amount <= 0) {
            return AwardOutcome::Ineligible;
        }

        $wasFirstEver = false;

        try {
            // Preserve pre-migration dedup history. This read is advisory: a
            // stale false cannot defeat the transaction's unique claim. Errors
            // propagate to a retryable failure, never "not previously awarded".
            $legacyAward = self::alreadyAwarded($contributor, $metric, $dedupKey);

            $outcome = DB::transaction(function () use ($contributor, $metric, $amount, $reason, $dedupKey, $legacyAward, $context, &$wasFirstEver): AwardOutcome {
                $locked = Contributor::query()->whereKey($contributor->getKey())->lockForUpdate()->firstOrFail();

                if ($locked->isOptedOut()) {
                    return AwardOutcome::Ineligible;
                }

                if (self::claimQuery($locked, $metric, $dedupKey)->exists()) {
                    return AwardOutcome::Duplicate;
                }

                // Decisions and counters share this transaction with XP/audit.
                // A rejected event stays rejected even when reconciliation runs later.
                if (! $legacyAward && ! ContributionLimits::allow($locked, $metric, $dedupKey, $context)) {
                    return AwardOutcome::Limited;
                }

                DB::table('xp_award_claims')->insert([
                    'contributor_id' => $locked->getKey(),
                    'metric' => $metric,
                    'event_hash' => hash('sha256', $dedupKey),
                    'event_key' => $dedupKey,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($legacyAward) {
                    return AwardOutcome::Duplicate;
                }

                $wasFirstEver = $locked->getTotalXp() === 0;
                LFL::award($metric)
                    ->to($locked)
                    ->amount($amount)
                    ->because($reason)
                    ->from('github')
                    ->withMeta(['dedup' => $dedupKey])
                    ->save();

                // Fail closed if event dispatch/logging is disabled or fails.
                // The synchronous LFL audit insertion is in this same transaction.
                if (! self::alreadyAwarded($locked, $metric, $dedupKey)) {
                    throw new RuntimeException('XP audit event was not persisted');
                }

                return AwardOutcome::Awarded;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            // Only call a conflict a duplicate if a committed matching claim
            // exists after rollback. Other uniqueness/storage errors must retry.
            try {
                if (! self::claimQuery($contributor, $metric, $dedupKey)->exists()) {
                    return self::failed($metric, $e);
                }
                $outcome = AwardOutcome::Duplicate;
            } catch (Throwable $lookupError) {
                return self::failed($metric, $lookupError);
            }
        } catch (Throwable $e) {
            return self::failed($metric, $e);
        }

        if ($outcome === AwardOutcome::Awarded || $outcome === AwardOutcome::Duplicate) {
            try {
                Cache::put("xp:{$contributor->id}:{$metric}:{$dedupKey}", true,
                    $dedupSeconds ?? (int) config('gamification.dedup_seconds', 31_536_000));
            } catch (Throwable $e) {
                // The durable outcome is already committed; cache failure cannot undo it.
                Log::warning('XP dedup cache unavailable', ['error_class' => $e::class]);
            }
        }

        if ($outcome === AwardOutcome::Awarded && $wasFirstEver) {
            try {
                LFL::grant('first-contribution')->to($contributor)->because('First tracked contribution')->save();
            } catch (Throwable) {
                // Achievement remains best-effort after the committed XP award.
            }
        }

        return $outcome;
    }

    private static function claimQuery(Contributor $contributor, string $metric, string $dedupKey): Builder
    {
        return DB::table('xp_award_claims')
            ->where('contributor_id', $contributor->getKey())
            ->where('metric', $metric)
            ->where('event_hash', hash('sha256', $dedupKey));
    }

    protected static function alreadyAwarded(Contributor $contributor, string $metric, string $dedupKey): bool
    {
        return EventLog::query()
            ->where('event_type', 'xp_awarded')
            ->where('awardable_type', $contributor->getMorphClass())
            ->where('awardable_id', $contributor->getKey())
            ->where('context->gamed_metric_slug', $metric)
            ->where('context->meta->dedup', $dedupKey)
            ->exists();
    }

    private static function failed(string $metric, Throwable $e): AwardOutcome
    {
        Log::warning('XP award failed', ['metric' => $metric, 'error_class' => $e::class]);

        return AwardOutcome::Failed;
    }
}
