<?php

use App\Lab\PrismTestRegistry;
use App\Lab\PrismTestRunner;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('lab:test {cases?* : Case IDs; omit to run all non-costly cases} {--include-costly}', function (PrismTestRegistry $registry, PrismTestRunner $runner): int {
    abort_unless(app()->environment('local'), 404);
    $requested = collect($this->argument('cases'));
    $cases = $requested->isEmpty() ? $registry->all() : $requested->map(fn (string $id) => $registry->find($id));
    $cases = $cases->filter(fn ($case) => $case !== null && ($this->option('include-costly') || ! $case->costly));

    if ($cases->isEmpty()) {
        $this->error('No matching test cases.');

        return self::FAILURE;
    }

    $failed = false;
    foreach ($cases as $case) {
        $this->line("Running {$case->id}...");
        $result = $runner->run($case);
        $failed = $failed || ! $result['passed'];
        $this->{$result['passed'] ? 'info' : 'error'}(($result['passed'] ? 'PASS' : 'FAIL')." {$case->id} ({$result['latency_ms']} ms)".($result['error'] ? ": {$result['error']}" : ''));
    }

    return $failed ? self::FAILURE : self::SUCCESS;
})->purpose('Run real Prism provider×feature telemetry checks');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
