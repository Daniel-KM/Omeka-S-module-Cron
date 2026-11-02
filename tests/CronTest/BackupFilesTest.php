<?php declare(strict_types=1);

namespace CronTest;

use CommonTest\AbstractHttpControllerTestCase;
use Cron\Module;
use Laminas\Log\Logger;
use Laminas\Log\Writer\Mock;

/**
 * Tests for the backup of "files/" and the upgrade of the module.
 */
class BackupFilesTest extends AbstractHttpControllerTestCase
{
    use CronTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        foreach (['cron_backup_dir', 'cron_backup_files_dir'] as $key) {
            $this->settings()->delete($key);
        }
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * @dataProvider insideOmekaDirProvider
     */
    public function testIsInsideOmekaDir(string $dir, string $filesPath, bool $expected): void
    {
        $this->assertSame($expected, Module::isInsideOmekaDir($dir, $filesPath));
    }

    public function insideOmekaDirProvider(): array
    {
        $files = OMEKA_PATH . '/files';
        return [
            'omeka' => [OMEKA_PATH . '/backup', $files, true],
            'files' => [$files, $files, true],
            'sub-folder not created' => [$files . '/backup/new/sub', $files, true],
            'parent in path' => [OMEKA_PATH . '/modules/../backup', $files, true],
            'files elsewhere' => ['/data/files/backup', '/data/files', true],
            'outside' => ['/srv/backup', $files, false],
            'outside via parent' => [OMEKA_PATH . '/../backup-omeka', $files, false],
            'same prefix' => [OMEKA_PATH . 'x', $files, false],
        ];
    }

    /**
     * @dataProvider backupFilesRefusedProvider
     */
    public function testBackupFilesRefused(string $dir, string $expected): void
    {
        $this->settings()->set('cron_backup_files_dir', $dir);
        $writer = new Mock();
        $logger = new Logger();
        $logger->addWriter($writer);

        $module = new Module();
        $module->setServiceLocator($this->getServiceLocator());
        $method = new \ReflectionMethod($module, 'backupFiles');
        $method->setAccessible(true);
        $method->invoke($module, $logger);

        $this->assertCount(1, $writer->events);
        $this->assertSame(Logger::ERR, $writer->events[0]['priority']);
        $this->assertStringContainsString($expected, $writer->events[0]['message']);
    }

    public function backupFilesRefusedProvider(): array
    {
        return [
            'no folder' => ['', 'No folder is set'],
            'inside omeka' => [OMEKA_PATH . '/backup', 'is inside Omeka'],
            'inside files' => [OMEKA_PATH . '/files/backup', 'is inside Omeka'],
        ];
    }

    public function testUpgradeMergesDefaultBackupDir(): void
    {
        $settings = $this->settings();
        $settings->set('cron_backup_dir', '/srv/backup');
        $settings->delete('cron_backup_files_dir');

        $this->upgrade();
        $this->assertSame('/srv/backup', $settings->get('cron_backup_files_dir'));
        $this->assertNull($settings->get('cron_backup_dir'));

        // A replay keeps the folder.
        $this->upgrade();
        $this->assertSame('/srv/backup', $settings->get('cron_backup_files_dir'));
    }

    public function testUpgradeKeepsSpecificBackupDir(): void
    {
        $settings = $this->settings();
        $settings->set('cron_backup_dir', '/srv/default');
        $settings->set('cron_backup_files_dir', '/srv/files');

        $this->upgrade();
        $this->assertSame('/srv/files', $settings->get('cron_backup_files_dir'));
        $this->assertNull($settings->get('cron_backup_dir'));
    }

    protected function upgrade(): void
    {
        $module = new Module();
        $module->upgrade('3.4.3', '3.4.4', $this->getServiceLocator());
    }
}
