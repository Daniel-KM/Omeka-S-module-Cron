<?php declare(strict_types=1);

namespace CronTest\Script;

use PHPUnit\Framework\TestCase;

/**
 * Tests that the cron script finds the root of Omeka, in particular when the
 * directory of the module is a symbolic link to a shared directory.
 */
class CronScriptTest extends TestCase
{
    protected string $tempDir;

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/cron_test_script_' . uniqid();
        mkdir($this->tempDir . '/shared/Cron/data/scripts', 0777, true);
        mkdir($this->tempDir . '/root/modules', 0777, true);
        copy(dirname(__DIR__, 3) . '/data/scripts/cron.php', $this->tempDir . '/shared/Cron/data/scripts/cron.php');
        symlink(OMEKA_PATH . '/bootstrap.php', $this->tempDir . '/root/bootstrap.php');
        symlink(OMEKA_PATH . '/application', $this->tempDir . '/root/application');
        symlink($this->tempDir . '/shared/Cron', $this->tempDir . '/root/modules/Cron');
    }

    public function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tempDir));
    }

    /**
     * @dataProvider callProvider
     */
    public function testRootFoundThroughSymbolicLink(string $cwd, string $script): void
    {
        $output = $this->runScript($cwd, $script);
        $this->assertStringContainsString('cron.php [arguments]', $output);
    }

    public function callProvider(): array
    {
        return [
            'absolute path' => ['', '/root/modules/Cron/data/scripts/cron.php'],
            'relative from root' => ['/root', 'modules/Cron/data/scripts/cron.php'],
            'from the directory of the script' => ['/root/modules/Cron/data/scripts', 'cron.php'],
        ];
    }

    public function testRootNotFound(): void
    {
        $output = $this->runScript('', '/shared/Cron/data/scripts/cron.php');
        $this->assertStringContainsString('The root of Omeka cannot be found', $output);
    }

    protected function runScript(string $cwd, string $script): string
    {
        $cwd = $this->tempDir . $cwd;
        $script = $script[0] === '/' ? $this->tempDir . $script : $script;
        // The variable PWD is set like a shell, that keeps the symbolic links.
        $command = sprintf(
            'cd %1$s && PWD=%1$s %2$s %3$s --help 2>&1',
            escapeshellarg($cwd),
            escapeshellarg(PHP_BINARY),
            escapeshellarg($script)
        );
        exec($command, $output);
        return implode("\n", $output);
    }
}
