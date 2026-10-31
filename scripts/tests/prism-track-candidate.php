<?php

declare(strict_types=1);

// Standalone controls intentionally stay outside the application test suite:
// the normal Tests workflow keeps its original 171 cases / 1509 assertions.
$temporaryRoot = sys_get_temp_dir().'/prism-track-controls-'.bin2hex(random_bytes(8));
if (! mkdir($temporaryRoot, 0700, true)) {
    throw new RuntimeException('Could not create the controls directory.');
}
$environment = [];
foreach (getenv() as $key => $value) {
    if (preg_match('/\A(PATH|SYSTEMROOT|WINDIR|COMSPEC|PATHEXT|TEMP|TMP|USERPROFILE|HOME|LANG|LC_ALL)\z/i', $key)) {
        $environment[$key] = $value;
    }
}
$environment += ['GITHUB_RUN_ID' => '1001', 'GITHUB_RUN_ATTEMPT' => '1', 'GIT_TERMINAL_PROMPT' => '0'];
$rows = [];

function demand(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function process(array $command, string $cwd, array $env, int $expected = 0): string
{
    $resource = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env, ['bypass_shell' => true]);
    demand(is_resource($resource), 'Could not start the control command.');
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($resource);
    demand($exit === $expected, 'Unexpected command exit '.$exit.' (expected '.$expected.'): '.($err ?: '').($out ?: ''));

    return $out;
}

function git(array $fixture, array $args): string
{
    return process(['git', ...$args], $fixture['cwd'], $fixture['env']);
}

function lockBytes(string $version): string
{
    return json_encode(['packages' => [['name' => 'particle-academy/prism', 'version' => $version]]], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n";
}

function fixture(string $name): array
{
    global $temporaryRoot, $environment;
    $folder = $temporaryRoot.'/'.$name;
    demand(mkdir($folder, 0700), 'Could not create the fixture.');
    $repo = $folder.'/repo';
    demand(mkdir($repo, 0700), 'Could not create the fixture checkout.');
    $f = ['folder' => $folder, 'cwd' => $repo, 'origin' => $folder.'/origin.git', 'env' => $environment];
    process(['git', 'init', '--bare', '--quiet', $f['origin']], $folder, $environment);
    git($f, ['init', '--quiet', '--initial-branch=main']);
    git($f, ['config', 'core.autocrlf', 'false']);
    git($f, ['config', 'commit.gpgsign', 'false']);
    git($f, ['config', 'user.name', 'Tracker control']);
    git($f, ['config', 'user.email', 'control@example.invalid']);
    mkdir($repo.'/scripts');
    foreach (['prism-track-candidate.php', 'prism-lock-message.php'] as $file) {
        demand(copy(dirname(__DIR__).'/'.$file, $repo.'/scripts/'.$file), 'Could not copy the actual script.');
    }
    $f['before'] = lockBytes('v0.143.0');
    $f['candidate'] = lockBytes('v0.144.0');
    file_put_contents($repo.'/composer.lock', $f['before']);
    git($f, ['add', '--', 'scripts', 'composer.lock']);
    git($f, ['commit', '--quiet', '-m', 'Fixture base']);
    git($f, ['remote', 'add', 'origin', $f['origin']]);
    git($f, ['push', '--quiet', 'origin', 'HEAD:refs/heads/main']);
    $f['base'] = trim(git($f, ['rev-parse', 'HEAD']));
    $f['stage'] = $folder.'/stage';
    $f['sealed'] = $folder.'/sealed';

    return $f;
}

function candidateCommand(array $f, string $command, string $directory, int $exit = 0, array $overrides = [], ?string $output = null): string
{
    $env = array_replace($f['env'], [
        'TRACK_BASE_SHA' => $f['base'],
        'TRACK_EXPECTED_SHA256' => hash('sha256', $f['candidate']),
        'TRACK_ARTIFACT_ID' => $command === 'publish' ? '2002' : '2001',
        'TRACK_STAGING_ARTIFACT_ID' => '2001',
        'TRACK_TESTS_RESULT' => 'success',
        'TRACK_BUILD_RESULT' => 'success',
    ], $overrides);
    $args = [PHP_BINARY, $f['cwd'].'/scripts/prism-track-candidate.php', $command, $directory];
    if ($output !== null) {
        $args[] = $output;
    }

    return process($args, $f['cwd'], $env, $exit);
}

function stage(array $f): void
{
    file_put_contents($f['cwd'].'/composer.lock', $f['candidate']);
    $out = candidateCommand($f, 'create', $f['stage']);
    demand(str_contains($out, 'changed=true'), 'Changed candidate was not recorded.');
    git($f, ['restore', '--', 'composer.lock']);
}

function unchanged(array $f): void
{
    demand(file_get_contents($f['cwd'].'/composer.lock') === $f['before'], 'Rejected candidate changed the local lock.');
    demand(trim(git($f, ['ls-remote', 'origin', 'refs/heads/main'])) === $f['base']."\trefs/heads/main", 'Rejected candidate changed remote main.');
    demand(git($f, ['--git-dir='.$f['origin'], 'show', 'refs/heads/main:composer.lock']) === $f['before'], 'Rejected candidate changed the remote lock.');
}

function scenario(string $name, callable $check): void
{
    global $rows;
    $f = fixture($name);
    $measured = $check($f) !== false;
    $rows[] = ['name' => $name, 'status' => $measured ? 'PASS' : 'UNMEASURED'];
    echo json_encode(end($rows), JSON_THROW_ON_ERROR)."\n";
}

try {
    scenario('exact-lock-only-publish', function ($f): void {
        stage($f);
        candidateCommand($f, 'apply', $f['stage']);
        demand(file_get_contents($f['cwd'].'/composer.lock') === $f['candidate'], 'Apply did not preserve exact candidate bytes.');
        demand(trim(git($f, ['diff', '--name-only'])) === 'composer.lock', 'Apply changed another tracked file.');
        git($f, ['restore', '--', 'composer.lock']);
        candidateCommand($f, 'seal', $f['stage'], output: $f['sealed']);
        candidateCommand($f, 'publish', $f['sealed']);
        demand(git($f, ['--git-dir='.$f['origin'], 'show', 'refs/heads/main:composer.lock']) === $f['candidate'], 'Published bytes differ from the validated lock.');
        demand(trim(git($f, ['--git-dir='.$f['origin'], 'diff', '--name-only', $f['base'], 'refs/heads/main'])) === 'composer.lock', 'Publisher wrote another file.');
    });
    scenario('unchanged-lock', function ($f): void {
        demand(str_contains(candidateCommand($f, 'create', $f['stage']), 'changed=false'), 'Unchanged lock was not a no-op.');
        demand(! file_exists($f['stage']), 'No-op produced a candidate artifact.');
        unchanged($f);
    });
    foreach (['tampered-lock', 'missing-receipt', 'extra-file', 'bad-schema', 'wrong-before'] as $name) {
        scenario($name, function ($f) use ($name): void {
            stage($f);
            if ($name === 'tampered-lock') {
                file_put_contents($f['stage'].'/composer.lock', "\n", FILE_APPEND);
            } elseif ($name === 'missing-receipt') {
                unlink($f['stage'].'/receipt.json');
            } elseif ($name === 'extra-file') {
                file_put_contents($f['stage'].'/unexpected.php', '<?php exit(17);');
            } else {
                $receipt = json_decode(file_get_contents($f['stage'].'/receipt.json'), true, flags: JSON_THROW_ON_ERROR);
                if ($name === 'bad-schema') {
                    $receipt['extra'] = true;
                } else {
                    $receipt['before_sha256'] = str_repeat('0', 64);
                }
                file_put_contents($f['stage'].'/receipt.json', json_encode($receipt, JSON_THROW_ON_ERROR));
            }
            candidateCommand($f, 'apply', $f['stage'], 1);
            unchanged($f);
        });
    }
    foreach (['wrong-digest' => ['TRACK_EXPECTED_SHA256' => str_repeat('0', 64)], 'wrong-base' => ['TRACK_BASE_SHA' => str_repeat('0', 40)], 'wrong-run' => ['GITHUB_RUN_ID' => '1002'], 'wrong-attempt' => ['GITHUB_RUN_ATTEMPT' => '2'], 'bad-artifact-id' => ['TRACK_ARTIFACT_ID' => '2001,2002']] as $name => $overrides) {
        scenario($name, function ($f) use ($overrides): void {
            stage($f);
            candidateCommand($f, 'apply', $f['stage'], 1, $overrides);
            unchanged($f);
        });
    }
    scenario('symlink-lock', function ($f): bool {
        stage($f);
        $target = $f['folder'].'/linked.lock';
        file_put_contents($target, $f['candidate']);
        unlink($f['stage'].'/composer.lock');
        if (! @symlink($target, $f['stage'].'/composer.lock')) {
            demand(getenv('TRACK_REQUIRE_SYMLINK_TEST') !== '1', 'Required symlink control could not execute.');
            unchanged($f);

            return false;
        }
        candidateCommand($f, 'apply', $f['stage'], 1);
        unchanged($f);

        return true;
    });
    foreach (['tests-failed' => ['TRACK_TESTS_RESULT' => 'failure'], 'build-failed' => ['TRACK_BUILD_RESULT' => 'failure'], 'tests-skipped' => ['TRACK_TESTS_RESULT' => 'skipped'], 'build-cancelled' => ['TRACK_BUILD_RESULT' => 'cancelled']] as $name => $overrides) {
        scenario($name, function ($f) use ($overrides): void {
            stage($f);
            candidateCommand($f, 'seal', $f['stage'], 1, $overrides, $f['sealed']);
            demand(! file_exists($f['sealed']), 'Failed validation produced a validated artifact.');
            candidateCommand($f, 'publish', $f['stage'], 1, $overrides);
            unchanged($f);
        });
    }
    scenario('unsealed-publish', function ($f): void {
        stage($f);
        candidateCommand($f, 'publish', $f['stage'], 1);
        unchanged($f);
    });
    scenario('sealed-other-attempt', function ($f): void {
        stage($f);
        candidateCommand($f, 'seal', $f['stage'], output: $f['sealed']);
        candidateCommand($f, 'publish', $f['sealed'], 1, ['GITHUB_RUN_ATTEMPT' => '2']);
        unchanged($f);
    });
    scenario('sealed-other-staging-id', function ($f): void {
        stage($f);
        candidateCommand($f, 'seal', $f['stage'], output: $f['sealed']);
        candidateCommand($f, 'publish', $f['sealed'], 1, ['TRACK_STAGING_ARTIFACT_ID' => '9999']);
        unchanged($f);
    });
    scenario('dirty-publisher', function ($f): void {
        stage($f);
        candidateCommand($f, 'seal', $f['stage'], output: $f['sealed']);
        file_put_contents($f['cwd'].'/unexpected.txt', 'not part of the validated tree');
        candidateCommand($f, 'publish', $f['sealed'], 1);
        unchanged($f);
    });
    scenario('main-moved', function ($f): void {
        stage($f);
        candidateCommand($f, 'seal', $f['stage'], output: $f['sealed']);
        file_put_contents($f['cwd'].'/maintainer.txt', 'concurrent maintainer commit');
        git($f, ['add', '--', 'maintainer.txt']);
        git($f, ['commit', '--quiet', '-m', 'Concurrent maintainer change']);
        $newMain = trim(git($f, ['rev-parse', 'HEAD']));
        git($f, ['push', '--quiet', 'origin', 'HEAD:refs/heads/main']);
        git($f, ['checkout', '--quiet', '--detach', $f['base']]);
        candidateCommand($f, 'publish', $f['sealed'], 1);
        demand(trim(git($f, ['ls-remote', 'origin', 'refs/heads/main'])) === $newMain."\trefs/heads/main", 'Publisher overwrote the concurrent main.');
        demand(git($f, ['--git-dir='.$f['origin'], 'show', 'refs/heads/main:composer.lock']) === $f['before'], 'Concurrent main lock changed.');
    });
    echo json_encode(['status' => 'PASS', 'controls' => count($rows), 'unmeasured' => count(array_filter($rows, fn ($r) => $r['status'] === 'UNMEASURED')), 'fixture_root' => $temporaryRoot], JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n".'Retained fixture root: '.$temporaryRoot."\n");
    exit(1);
}
