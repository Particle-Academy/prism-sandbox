<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class PrismLockMessageTest extends TestCase
{
    public function test_prism_version_change_keeps_the_existing_message_even_with_transitive_changes(): void
    {
        $this->assertSame("chore: track latest prism (v0.131.0)\n", $this->message($this->lock('v0.130.0', '3.14.0'), $this->lock('v0.131.0', '3.14.1')));
    }

    public function test_carbon_only_update_does_not_claim_a_prism_bump(): void
    {
        $this->assertSame("chore: refresh composer lock (prism version v0.130.0 unchanged)\n", $this->message($this->lock('v0.130.0', '3.14.0'), $this->lock('v0.130.0', '3.14.1')));
    }

    public function test_unchanged_lock_produces_no_commit_message(): void
    {
        $lock = $this->lock('v0.130.0', '3.14.1');
        $this->assertSame('', $this->message($lock, $lock));
    }

    public function test_metadata_only_change_is_still_described_as_a_lock_refresh(): void
    {
        $lock = $this->lock('v0.130.0', '3.14.1');
        $this->assertSame("chore: refresh composer lock (prism version v0.130.0 unchanged)\n", $this->message($lock, $lock."\n"));
    }

    public function test_missing_prism_fails_instead_of_producing_a_misleading_message(): void
    {
        $process = $this->runScript($this->lock('v0.130.0', '3.14.0'), '{"packages":[]}');
        $this->assertFalse($process->isSuccessful());
        $this->assertSame('', $process->getOutput());
        $this->assertStringContainsString('valid particle-academy/prism version', $process->getErrorOutput());
    }

    private function lock(string $prism, string $carbon): string
    {
        return json_encode(['packages' => [
            ['name' => 'particle-academy/prism', 'version' => $prism],
            ['name' => 'nesbot/carbon', 'version' => $carbon],
        ]], JSON_THROW_ON_ERROR);
    }

    private function message(string $before, string $after): string
    {
        $process = $this->runScript($before, $after);
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        return $process->getOutput();
    }

    private function runScript(string $before, string $after): Process
    {
        $old = tempnam(sys_get_temp_dir(), 'prism-before-');
        $new = tempnam(sys_get_temp_dir(), 'prism-after-');
        try {
            file_put_contents($old, $before);
            file_put_contents($new, $after);
            $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/scripts/prism-lock-message.php', $old, $new]);
            $process->run();

            return $process;
        } finally {
            unlink($old);
            unlink($new);
        }
    }
}
