<?php

declare(strict_types=1);

namespace PrismSandbox\Tracking;

use RuntimeException;
use Throwable;

// Deliberately no Composer bootstrap: the write job must never execute the
// dependency graph that the read-only jobs are evaluating.
final class Candidate
{
    private const KEYS = ['schema', 'base', 'run_id', 'run_attempt', 'before_sha256', 'candidate_sha256', 'prism_version', 'validation', 'staging_artifact_id'];

    public static function command(array $args): void
    {
        $command = $args[1] ?? '';
        if (! in_array($command, ['create', 'apply', 'seal', 'publish'], true) || count($args) !== ($command === 'seal' ? 4 : 3)) {
            throw new RuntimeException('Usage: prism-track-candidate.php create|apply|publish DIRECTORY, or seal DIRECTORY OUTPUT');
        }

        $root = getcwd();
        if ($root === false || realpath(trim(self::run(['git', 'rev-parse', '--show-toplevel']))) !== realpath($root)) {
            throw new RuntimeException('Run from the checkout root.');
        }

        $base = trim(self::run(['git', 'rev-parse', 'HEAD']));
        self::match($base, '/\A[0-9a-f]{40}\z/', 'checkout SHA');
        $before = self::run(['git', 'show', $base.':composer.lock']);
        $runId = self::positiveEnv('GITHUB_RUN_ID');
        $attempt = self::positiveEnv('GITHUB_RUN_ATTEMPT');

        if ($command === 'create') {
            self::onlyLockMayDiffer();
            $bytes = self::file($root.'/composer.lock', 16000000);
            $version = self::version($bytes);
            if ($bytes === $before) {
                self::outputs(['changed' => 'false', 'base' => $base]);

                return;
            }

            $receipt = [
                'schema' => 1,
                'base' => $base,
                'run_id' => $runId,
                'run_attempt' => $attempt,
                'before_sha256' => hash('sha256', $before),
                'candidate_sha256' => hash('sha256', $bytes),
                'prism_version' => $version,
                'validation' => null,
                'staging_artifact_id' => null,
            ];
            self::write($args[2], $bytes, $receipt);
            self::outputs(['changed' => 'true', 'base' => $base, 'candidate_sha256' => $receipt['candidate_sha256'], 'prism_version' => $version]);

            return;
        }

        $expectedBase = self::env('TRACK_BASE_SHA');
        $expectedDigest = self::env('TRACK_EXPECTED_SHA256');
        self::match($expectedBase, '/\A[0-9a-f]{40}\z/', 'expected base');
        self::match($expectedDigest, '/\A[0-9a-f]{64}\z/', 'expected digest');
        $artifactId = self::positiveEnv('TRACK_ARTIFACT_ID');
        if ($base !== $expectedBase) {
            throw new RuntimeException('Checkout does not match the expected base.');
        }

        [$bytes, $receipt] = self::read($args[2]);
        if ($receipt['schema'] !== 1 || $receipt['base'] !== $base || $receipt['run_id'] !== $runId || $receipt['run_attempt'] !== $attempt
            || $receipt['before_sha256'] !== hash('sha256', $before) || $receipt['candidate_sha256'] !== $expectedDigest
            || ! hash_equals($expectedDigest, hash('sha256', $bytes)) || $receipt['prism_version'] !== self::version($bytes)
            || $receipt['candidate_sha256'] === $receipt['before_sha256']) {
            throw new RuntimeException('Candidate identity, context or digest mismatch.');
        }

        if ($command === 'apply') {
            if ($receipt['validation'] !== null || $receipt['staging_artifact_id'] !== null) {
                throw new RuntimeException('Expected an unvalidated staging candidate.');
            }
            self::clean();
            if (self::file($root.'/composer.lock', 16000000) !== $before) {
                throw new RuntimeException('Original lock bytes changed.');
            }
            self::replaceLock($root, $bytes);
            self::onlyLockMayDiffer();

            return;
        }

        if ($command === 'seal') {
            self::clean();
            if ($receipt['validation'] !== null || $receipt['staging_artifact_id'] !== null
                || self::env('TRACK_TESTS_RESULT') !== 'success' || self::env('TRACK_BUILD_RESULT') !== 'success') {
                throw new RuntimeException('Both reusable validation jobs must have succeeded.');
            }
            $receipt['validation'] = ['tests' => 'success', 'build' => 'success'];
            $receipt['staging_artifact_id'] = $artifactId;
            self::write($args[3], $bytes, $receipt);

            return;
        }

        if ($receipt['validation'] !== ['tests' => 'success', 'build' => 'success']
            || $receipt['staging_artifact_id'] !== self::positiveEnv('TRACK_STAGING_ARTIFACT_ID')
            || self::env('TRACK_TESTS_RESULT') !== 'success' || self::env('TRACK_BUILD_RESULT') !== 'success') {
            throw new RuntimeException('Publisher requires this attempt\'s validated candidate.');
        }
        self::clean();
        $remote = trim(self::run(['git', 'ls-remote', '--exit-code', 'origin', 'refs/heads/main']));
        if (! preg_match('/\A'.preg_quote($base, '/').'\s+refs\/heads\/main\z/', $remote)) {
            throw new RuntimeException('Remote main changed after validation.');
        }

        $oldFile = tempnam(sys_get_temp_dir(), 'prism-track-before-');
        if ($oldFile === false) {
            throw new RuntimeException('Could not create the original-lock fixture.');
        }
        try {
            self::put($oldFile, $before);
            self::replaceLock($root, $bytes);
            $message = trim(self::run([PHP_BINARY, __DIR__.'/prism-lock-message.php', $oldFile, $root.'/composer.lock']));
            if ($message === '' || preg_match('/[\r\n]/', $message)) {
                throw new RuntimeException('Invalid lock commit message.');
            }
            self::onlyLockMayDiffer();
            self::run(['git', 'config', 'user.name', 'github-actions[bot]']);
            self::run(['git', 'config', 'user.email', '41898282+github-actions[bot]@users.noreply.github.com']);
            self::run(['git', 'add', '--', 'composer.lock']);
            if (trim(self::run(['git', 'diff', '--cached', '--name-only'])) !== 'composer.lock') {
                throw new RuntimeException('Refusing to stage anything except composer.lock.');
            }
            self::run(['git', 'commit', '-m', $message]);
            // Normal fast-forward push. A main update after the earlier check
            // rejects this push; never force or overwrite a maintainer commit.
            self::run(['git', 'push', 'origin', 'HEAD:refs/heads/main']);
        } finally {
            unlink($oldFile);
        }
    }

    private static function read(string $directory): array
    {
        if (is_link($directory) || ! is_dir($directory)) {
            throw new RuntimeException('Candidate directory must be a real directory.');
        }
        $names = array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
        sort($names);
        if ($names !== ['composer.lock', 'receipt.json']) {
            throw new RuntimeException('Candidate must contain exactly composer.lock and receipt.json.');
        }
        $bytes = self::file($directory.'/composer.lock', 16000000);
        $receipt = json_decode(self::file($directory.'/receipt.json', 8192), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($receipt) || array_keys($receipt) !== self::KEYS) {
            throw new RuntimeException('Unexpected candidate receipt schema.');
        }

        return [$bytes, $receipt];
    }

    private static function write(string $directory, string $bytes, array $receipt): void
    {
        if (file_exists($directory) || is_link($directory) || ! mkdir($directory, 0700, true)) {
            throw new RuntimeException('Candidate output must not already exist.');
        }
        self::put($directory.'/composer.lock', $bytes);
        self::put($directory.'/receipt.json', json_encode($receipt, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n");
    }

    private static function replaceLock(string $root, string $bytes): void
    {
        self::file($root.'/composer.lock', 16000000);
        self::put($root.'/composer.lock', $bytes);
    }

    private static function version(string $bytes): string
    {
        $lock = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
        $matches = array_values(array_filter($lock['packages'] ?? [], fn ($p) => ($p['name'] ?? null) === 'particle-academy/prism'));
        if (count($matches) !== 1 || ! is_string($matches[0]['version'] ?? null)) {
            throw new RuntimeException('Candidate must contain exactly one Prism version.');
        }
        $version = $matches[0]['version'];
        self::match($version, '/\Av?[0-9]+\.[0-9]+\.[0-9]+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?\z/', 'Prism version');

        return $version;
    }

    private static function file(string $file, int $maxBytes): string
    {
        clearstatcache(true, $file);
        if (is_link($file) || ! is_file($file) || filesize($file) < 1 || filesize($file) > $maxBytes) {
            throw new RuntimeException('Expected a bounded regular candidate file.');
        }
        $bytes = file_get_contents($file);
        if ($bytes === false) {
            throw new RuntimeException('Could not read the candidate file.');
        }

        return $bytes;
    }

    private static function put(string $file, string $bytes): void
    {
        if (file_put_contents($file, $bytes) !== strlen($bytes)) {
            throw new RuntimeException('Could not write all candidate bytes.');
        }
    }

    private static function env(string $name): string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            throw new RuntimeException('Missing context: '.$name);
        }

        return $value;
    }

    private static function positiveEnv(string $name): string
    {
        $value = self::env($name);
        self::match($value, '/\A[1-9][0-9]*\z/', $name);

        return $value;
    }

    private static function match(string $value, string $pattern, string $label): void
    {
        if (! preg_match($pattern, $value)) {
            throw new RuntimeException('Invalid '.$label.'.');
        }
    }

    private static function clean(): void
    {
        if (trim(self::run(['git', 'status', '--porcelain'])) !== '') {
            throw new RuntimeException('Expected a clean checkout.');
        }
    }

    private static function onlyLockMayDiffer(): void
    {
        $changes = trim(self::run(['git', 'diff', '--name-only']));
        if (! in_array($changes, ['', 'composer.lock'], true) || trim(self::run(['git', 'diff', '--cached', '--name-only'])) !== '') {
            throw new RuntimeException('Only the unstaged composer.lock may differ.');
        }
    }

    private static function outputs(array $values): void
    {
        foreach ($values as $key => $value) {
            $line = $key.'='.$value."\n";
            $file = getenv('GITHUB_OUTPUT');
            if ($file !== false && $file !== '') {
                if (file_put_contents($file, $line, FILE_APPEND) !== strlen($line)) {
                    throw new RuntimeException('Could not record candidate output.');
                }
            }
            echo $line;
        }
    }

    private static function run(array $command): string
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, options: ['bypass_shell' => true]);
        if (! is_resource($process)) {
            throw new RuntimeException('Could not start a tracker command.');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0 || $stdout === false || $stderr === false) {
            // Do not echo credential helpers, remote URLs or environment data.
            throw new RuntimeException('Tracker command failed (exit '.$exit.').');
        }

        return $stdout;
    }
}

try {
    Candidate::command($argv);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
