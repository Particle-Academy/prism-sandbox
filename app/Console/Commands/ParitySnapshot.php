<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Parity\SnapshotBuilder;
use App\Parity\SnapshotStore;
use Illuminate\Console\Command;

/**
 * Collects the public parity dashboard's data and writes the snapshot file.
 *
 * Scheduled (routes/console.php); it is never run per page view, because the
 * sources are rate-limited and unauthenticated on purpose. It reads public
 * GitHub endpoints only, holds no token, and writes no database rows.
 */
class ParitySnapshot extends Command
{
    protected $signature = 'parity:snapshot';

    protected $description = 'Collect language parity, provider capability, drift and protocol data from public sources for /parity.';

    public function handle(SnapshotBuilder $builder): int
    {
        $written = SnapshotStore::default()->write($builder->build());

        $sections = [
            'parity' => $written['parity'],
            'providers' => $written['providers'],
            'drift' => $written['drift'],
            'mcp' => $written['protocols']['mcp'],
            'acp' => $written['protocols']['acp'],
        ];

        $failed = 0;
        foreach ($sections as $name => $section) {
            $this->line(sprintf('%-10s %s', $name, $section['status']));
            foreach ($section['reads'] as $read) {
                if (! $read['ok']) {
                    $this->warn("  could not read {$read['label']}: {$read['error']}");
                }
            }
            if ($section['status'] !== 'ok') {
                $failed++;
            }
        }

        // Non-zero when anything was not fully read, so a scheduler log says so.
        // The snapshot is still written: the page shows what failed as unknown or stale.
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
