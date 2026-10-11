<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What happened when an award was attempted.
 *
 * `award()` used to return a bare `bool` for five distinct outcomes, four of
 * which mean "deliberately skipped, never retry" and one of which means
 * "transient, please retry". A caller holding `false` could not tell them
 * apart.
 *
 * That was survivable only because the retry semantics were carried by a SIDE
 * EFFECT rather than by the return value: a skip writes the cooldown key, a
 * failure deliberately leaves it unset so a redelivery tries again. A contract
 * that lives in which cache key did or did not get written is one no reader can
 * see and no test can assert without reproducing the whole path — it worked,
 * and it was one refactor from not working, with nothing to catch it.
 *
 * It had already cost something: the gamification backfill reported every
 * non-award as "already awarded", so a run where the taxonomy was unseeded
 * counted its failures as duplicates and looked clean.
 *
 * Found by a rule the Fancy team wrote to hunt this shape — an operation with
 * several failure modes reporting one bit — after we traded three instances of
 * it in one afternoon.
 */
enum AwardOutcome: string
{
    /** XP landed. */
    case Awarded = 'awarded';

    /** No contributor, opted out, or a non-positive amount. Never retry. */
    case Ineligible = 'ineligible';

    /** Already awarded, per the cache or the durable event log. Never retry. */
    case Duplicate = 'duplicate';

    /** Persistently refused by a contribution cap; backfill must not restore it. */
    case Limited = 'limited';

    /** The award threw. The cooldown is deliberately unset so a redelivery retries. */
    case Failed = 'failed';

    public function awarded(): bool
    {
        return $this === self::Awarded;
    }

    /**
     * Whether re-attempting this could ever produce a different answer.
     *
     * The distinction the bare bool destroyed, and the reason this enum exists.
     */
    public function retryable(): bool
    {
        return $this === self::Failed;
    }
}
