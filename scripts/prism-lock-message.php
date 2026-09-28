<?php

declare(strict_types=1);

// No Composer bootstrap: the tracker runs with --no-scripts and this decision
// needs only the two lockfiles, not code from the dependencies being updated.
try {
    if ($argc !== 3) {
        throw new RuntimeException('Usage: php prism-lock-message.php BEFORE AFTER');
    }

    $before = file_get_contents($argv[1]);
    $after = file_get_contents($argv[2]);
    if ($before === false || $after === false) {
        throw new RuntimeException('Could not read both lockfiles.');
    }

    $version = static function (string $contents): string {
        $lock = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        foreach ($lock['packages'] ?? [] as $package) {
            if (($package['name'] ?? null) === 'particle-academy/prism') {
                $value = $package['version'] ?? null;
                if (is_string($value) && $value !== '' && ! preg_match('/[\r\n]/', $value)) {
                    return $value;
                }
            }
        }

        throw new RuntimeException('Lockfile must contain a valid particle-academy/prism version.');
    };

    $old = $version($before);
    $new = $version($after);
    if ($before === $after) {
        exit(0);
    }

    echo $old !== $new
        ? "chore: track latest prism ({$new})\n"
        : "chore: refresh composer lock (prism version {$new} unchanged)\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
