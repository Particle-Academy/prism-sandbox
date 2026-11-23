<?php

declare(strict_types=1);

namespace App\Parity;

/**
 * Where the snapshot lives between collections: one JSON file on local disk.
 *
 * A file rather than a table, on purpose. This app shares its database with
 * another, and a dashboard cache is not worth a migration against it.
 *
 * When a section fails to collect but the previous snapshot had it, the old
 * data is kept and marked `stale`, with the date it was last read. The page
 * then shows what was known and how old it is, which is more useful than a
 * blank and more honest than pretending it is current. A stale section is
 * never drawn green.
 */
final class SnapshotStore
{
    /** Section paths inside a snapshot that are carried forward independently. */
    private const SECTIONS = [['parity'], ['providers'], ['drift'], ['protocols', 'mcp'], ['protocols', 'acp']];

    public function __construct(private readonly string $path) {}

    public static function default(): self
    {
        return new self(storage_path('app/parity-dashboard.json'));
    }

    /** @return array<string, mixed>|null */
    public function read(): ?array
    {
        if (! is_file($this->path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($this->path), true);

        return is_array($json) && ($json['schema'] ?? null) === SnapshotBuilder::SCHEMA ? $json : null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed> what was written
     */
    public function write(array $snapshot): array
    {
        $previous = $this->read();
        if ($previous !== null) {
            foreach (self::SECTIONS as $path) {
                $new = data_get($snapshot, $path);
                $old = data_get($previous, $path);
                if (is_array($new) && is_array($old) && $new['status'] === 'failed' && is_array($old['data'] ?? null)) {
                    data_set($snapshot, $path, [
                        'status' => 'stale',
                        'fetched_at' => $new['fetched_at'],
                        'last_ok_at' => $old['last_ok_at'] ?? $old['fetched_at'] ?? null,
                        'reads' => $new['reads'],
                        'data' => $old['data'],
                    ]);
                }
            }
        }

        $directory = dirname($this->path);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $temporary = $this->path.'.'.bin2hex(random_bytes(4)).'.tmp';
        file_put_contents($temporary, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        rename($temporary, $this->path);

        return $snapshot;
    }
}
