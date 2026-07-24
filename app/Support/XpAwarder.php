<?php

namespace App\Support;

use App\Models\Contributor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use LaravelFunLab\Facades\LFL;
use Throwable;

/**
 * Idempotent, fail-safe wrapper around LFL::award() for GitHub contributions.
 *
 * XP must never break the webhook, so every failure is swallowed. A per-object
 * cache cooldown keyed on the GitHub object identity de-duplicates replays and
 * redeliveries; the cooldown is only set once an award actually lands, so a
 * transient failure can still be retried on redelivery.
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
}
