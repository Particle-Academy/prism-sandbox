<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DemoRequestBudget
{
    /** Reserve one provider attempt. Zero admits; positive seconds mean retry later. */
    public function reserve(string $provider): int
    {
        $windows = [
            ['name' => 'minute', 'limit' => max(0, (int) config('demo.requests_per_minute', 10))],
            ['name' => 'day', 'limit' => max(0, (int) config('demo.requests_per_day', 100))],
        ];

        // Exactly two rows per provider, reused on rollover. The shared database
        // and fixed lock order arbitrate all workers, independent of IP/cache.
        return DB::transaction(function () use ($provider, $windows): int {
            $buckets = [];
            foreach ($windows as $window) {
                $insertedAt = CarbonImmutable::now('UTC');
                DB::table('demo_request_budgets')->upsert([
                    'provider' => $provider, 'window' => $window['name'],
                    'period_started_at' => $insertedAt->toDateTimeString(), 'used' => 0,
                    'created_at' => $insertedAt, 'updated_at' => $insertedAt,
                ], ['provider', 'window'], ['provider']);
                $buckets[$window['name']] = DB::table('demo_request_budgets')->where('provider', $provider)
                    ->where('window', $window['name'])->lockForUpdate()->first();
            }

            // Read the clock after acquiring BOTH locks, including on retries.
            // A delayed request must never roll a newer counter back in time.
            $now = CarbonImmutable::now('UTC');
            $retryAfter = 0;
            foreach ($windows as $window) {
                $start = $window['name'] === 'minute' ? $now->startOfMinute() : $now->startOfDay();
                $end = $window['name'] === 'minute' ? $start->addMinute() : $start->addDay();
                $query = DB::table('demo_request_budgets')->where('provider', $provider)->where('window', $window['name']);
                $bucket = $buckets[$window['name']];
                $used = (int) $bucket->used;
                if (CarbonImmutable::parse($bucket->period_started_at, 'UTC')->getTimestamp() !== $start->getTimestamp()) {
                    $used = 0;
                    $query->update(['period_started_at' => $start->toDateTimeString(), 'used' => 0, 'updated_at' => $now]);
                }
                if ($used >= $window['limit']) {
                    $retryAfter = max($retryAfter, $end->getTimestamp() - $now->getTimestamp(), 1);
                }
            }
            if ($retryAfter === 0) {
                foreach ($windows as $window) {
                    DB::table('demo_request_budgets')->where('provider', $provider)->where('window', $window['name'])
                        ->increment('used', 1, ['updated_at' => $now]);
                }
            }

            return $retryAfter;
        }, 3);
    }
}
