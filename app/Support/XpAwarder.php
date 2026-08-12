<?php

namespace App\Support;

use App\Models\Contributor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use LaravelFunLab\Facades\LFL;
use LaravelFunLab\Models\EventLog;
use Throwable;

/**
 * Idempotent, fail-safe wrapper around LFL::award() for GitHub contributions.
 *
 * XP must never break the webhook, so every failure is swallowed.
 *
 * De-duplication is keyed on the GitHub object identity and checked in two
 * layers. The cache is a FAST PATH, not the record: it is disposable, and a
 * `cache:clear` during a deploy used to wipe every cooldown, so the next
 * backfill re-awarded events that had already been granted and inflated
 * everyone's XP. The authority is LFL's own event log, which persists the
 * dedup key in `context.meta.dedup` for every award it lands.
 *
 * A cooldown is only set once an award actually lands, so a transient failure
 * can still be retried on redelivery.
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
    ): bool {
        if (! $contributor instanceof Contributor || $contributor->isOptedOut()) {
            return false;
        }

        if ($amount <= 0) {
            return false;
        }

        $dedupSeconds ??= (int) config('gamification.dedup_seconds', 31_536_000);
        $cacheKey = "xp:{$contributor->id}:{$metric}:{$dedupKey}";

        if (Cache::has($cacheKey)) {
            return false;
        }

        // Cache miss is not proof this is new — it is also what a cache:clear
        // looks like. Fall through to the durable record before awarding, and
        // repopulate the fast path so the next check is cheap again.
        if (self::alreadyAwarded($contributor, $metric, $dedupKey)) {
            Cache::put($cacheKey, true, $dedupSeconds);

            return false;
        }

        $wasFirstEver = $contributor->getTotalXp() === 0;

        try {
            LFL::award($metric)
                ->to($contributor)
                ->amount($amount)
                ->because($reason)
                ->from('github')
                ->withMeta(['dedup' => $dedupKey])
                ->save();
        } catch (Throwable $e) {
            // Leave the cooldown unset so a redelivery retries once the taxonomy
            // is seeded / the transient cause clears.
            Log::warning('XP award failed', [
                'metric' => $metric,
                'dedup' => $dedupKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        Cache::put($cacheKey, true, $dedupSeconds);

        if ($wasFirstEver) {
            try {
                LFL::grant('first-contribution')->to($contributor)->because('First tracked contribution')->save();
            } catch (Throwable) {
                // Achievement is best-effort; never fail the award over it.
            }
        }

        return true;
    }

    /**
     * Whether this exact contribution has already been awarded, according to
     * the durable record rather than the cache.
     *
     * LFL writes every landed award to its event log and stores the dedup key
     * we pass via withMeta() at `context.meta.dedup`, alongside the metric
     * slug. That survives a cache flush, a redeploy, and a fresh cache store,
     * which is the whole point of consulting it.
     */
    protected static function alreadyAwarded(Contributor $contributor, string $metric, string $dedupKey): bool
    {
        try {
            return EventLog::query()
                ->where('event_type', 'xp_awarded')
                ->where('awardable_type', $contributor->getMorphClass())
                ->where('awardable_id', $contributor->getKey())
                ->where('context->gamed_metric_slug', $metric)
                ->where('context->meta->dedup', $dedupKey)
                ->exists();
        } catch (Throwable $e) {
            // Never let a dedup lookup break a webhook. Reporting "not yet
            // awarded" risks a duplicate; reporting "already awarded" would
            // silently drop a real contribution. A duplicate is the lesser
            // harm and is visible, so fail open and record why.
            Log::warning('XP dedup lookup failed', [
                'metric' => $metric,
                'dedup' => $dedupKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
