<?php declare(strict_types=1);

/*
 * Copyright 2022-2026 Daniel Berthereau
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software. You can use, modify and/or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software’s author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user’s attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software’s suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace Cron;

use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Omeka\Module\AbstractModule;

/**
 * Cron.
 *
 * Provides a cron system for omeka that can:
 * - Execute tasks on page load (fallback when no server cron available);
 * - Be triggered via server cron or webcron;
 * - Allow modules to register their own tasks via event.
 *
 * When EasyAdmin is installed, integrates into its admin menu.
 * Otherwise, provides its own admin menu entry.
 *
 * @copyright Daniel Berthereau, 2022-2026
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(\Laminas\ServiceManager\ServiceLocatorInterface $services): void
    {
        $this->requireEasyAdminVersion($services);
    }

    public function upgrade($oldVersion, $newVersion, \Laminas\ServiceManager\ServiceLocatorInterface $services): void
    {
        // The new cron task model (real id + params) is shared with EasyAdmin.
        $this->requireEasyAdminVersion($services);

        $settings = $services->get('Omeka\Settings');

        if (version_compare($oldVersion, '3.4.3', '<')) {
            // Migrate cron tasks from flattened ids ("session_8d") to real ids
            // plus a clean params map ("session" + {age: "8d"}).
            $cronSettings = $settings->get('cron', []);
            if (!empty($cronSettings['tasks'])) {
                $migrated = [];
                foreach ($cronSettings['tasks'] as $taskId => $conf) {
                    [$realId, $params] = $this->migrateCronTaskId((string) $taskId);
                    $entry = is_array($conf) ? $conf : ['enabled' => true];
                    unset($entry['parent_task']);
                    if ($params) {
                        $entry['params'] = $params;
                    }
                    $migrated[$realId] = $entry;
                }
                $cronSettings['tasks'] = $migrated;
                $settings->set('cron', $cronSettings);
            }
        }
    }

    /**
     * Block install/upgrade when EasyAdmin is active but too old for the shared
     * cron task model.
     */
    protected function requireEasyAdminVersion(\Laminas\ServiceManager\ServiceLocatorInterface $services): void
    {
        $module = $services->get('Omeka\ModuleManager')->getModule('EasyAdmin');
        if (!$module || $module->getState() !== \Omeka\Module\Manager::STATE_ACTIVE) {
            return;
        }
        $version = $module->getIni('version');
        if ($version && version_compare($version, '3.4.46', '<')) {
            throw new \Omeka\Module\Exception\ModuleCannotInstallException(sprintf(
                'The module EasyAdmin should be upgraded to version %s or later.', // @translate
                '3.4.46'
            ));
        }
    }

    /**
     * Map a legacy flattened cron task id to a real id and a clean params map.
     *
     * @return array{0: string, 1: array}
     */
    protected function migrateCronTaskId(string $taskId): array
    {
        if (strncmp($taskId, 'session_', 8) === 0) {
            return ['session', ['age' => substr($taskId, 8)]];
        }
        if ($taskId === 'backup_db_compressed' || $taskId === 'backup_db_plain') {
            return ['backup_database', ['format' => substr($taskId, 10)]];
        }
        if ($taskId === 'backup_files_full' || $taskId === 'backup_files_config') {
            return ['backup_files', ['scope' => substr($taskId, 13)]];
        }
        return [$taskId, []];
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        /** @var \Omeka\Permissions\Acl $acl */
        $acl = $this->getServiceLocator()->get('Omeka\Acl');


        // Anybody can access to cron controller, since cron is on load page.
        $acl
            ->allow(
                null,
                [Controller\Admin\CronController::class]
            );
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // Cron via view.layout as fallback for users without server access.
        // Ideally, use server cron, systemd timer, or webcron instead.
        $sharedEventManager->attach(
            '*',
            'view.layout',
            [$this, 'handleCron']
        );

        // Handle the built-in "files/" backup task.
        $sharedEventManager->attach(
            \Cron\Job\CronTasks::class,
            'cron.execute',
            [$this, 'handleCronExecute']
        );
    }

    /**
     * Execute the module's own cron tasks (currently the "files/" backup).
     */
    public function handleCronExecute(Event $event): void
    {
        if ($event->getParam('task_id') !== 'backup_files_dir') {
            return;
        }
        $this->backupFiles($event->getParam('logger'));
        $event->setParam('handled', true);
    }

    /**
     * Archive the local "files/" directory into the configured backup folder.
     *
     * The destination is the specific files folder, else the default backup
     * folder, else "files/backup". It must be outside "files/", otherwise the
     * archive would include itself.
     *
     * @param \Laminas\Log\Logger $logger
     */
    protected function backupFiles($logger): void
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $config = $services->get('Config');

        $filesPath = $config['file_store']['local']['base_path'] ?? null;
        $filesPath = $filesPath ?: (OMEKA_PATH . '/files');

        $dest = trim((string) $settings->get('cron_backup_files_dir', ''))
            ?: trim((string) $settings->get('cron_backup_dir', ''))
            ?: ($filesPath . '/backup');

        $filesReal = rtrim(realpath($filesPath) ?: $filesPath, '/') . '/';
        $destReal = rtrim(realpath($dest) ?: $dest, '/') . '/';
        if (strpos($destReal, $filesReal) === 0) {
            $logger->err(
                'The files backup destination "{path}" is inside "files/". Set a folder outside "files/" in the cron settings.', // @translate
                ['path' => $dest]
            );
            return;
        }

        if (!is_dir($dest)) {
            @mkdir($dest, 0775, true);
        }
        if (!is_dir($dest) || !is_writable($dest)) {
            $logger->err(
                'The files backup destination "{path}" is not writable.', // @translate
                ['path' => $dest]
            );
            return;
        }

        $tar = $this->findCommandPath('tar');
        if (!$tar) {
            $logger->err(
                'The command "tar" is required to back up the "files/" directory.' // @translate
            );
            return;
        }

        $target = rtrim($dest, '/') . '/files-' . date('Ymd-His') . '.tar.gz';
        $cmd = sprintf(
            '%s -czf %s -C %s %s 2>/dev/null',
            escapeshellcmd($tar),
            escapeshellarg($target),
            escapeshellarg(dirname($filesPath)),
            escapeshellarg(basename($filesPath))
        );

        $logger->info('Backing up "files/" to {path}…', ['path' => $target]); // @translate

        $exitCode = null;
        @exec($cmd, $out, $exitCode);
        if ($exitCode === 0 && file_exists($target) && filesize($target) > 0) {
            $logger->notice(
                'Backup of "files/" completed: {path} ({size} bytes).', // @translate
                ['path' => $target, 'size' => number_format((int) filesize($target), 0, '.', ' ')]
            );
        } else {
            $logger->err(
                'Backup of "files/" failed for {path} (exit code {code}).', // @translate
                ['path' => $target, 'code' => $exitCode]
            );
        }
    }

    /**
     * Find the path of a command quietly (proc_open, then exec, then
     * shell_exec), respecting disabled functions, without logging.
     */
    protected function findCommandPath(string $command): ?string
    {
        $cmd = sprintf('command -v %s 2>/dev/null', escapeshellarg($command));
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        $available = function ($name) use ($disabled) {
            return function_exists($name) && !in_array($name, $disabled, true);
        };

        $output = '';
        if ($available('proc_open')) {
            $proc = @proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, getcwd());
            if (is_resource($proc)) {
                fclose($pipes[0]);
                $output = (string) stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);
            }
        } elseif ($available('exec')) {
            $lines = [];
            @exec($cmd, $lines);
            $output = implode("\n", $lines);
        } elseif ($available('shell_exec')) {
            $output = (string) @shell_exec($cmd);
        }

        $output = trim($output);
        return $output === '' ? null : $output;
    }

    /**
     * Handle cron tasks on page load (fallback for users without server cron).
     *
     * This method runs based on configured frequency (default: daily).
     * For more precise scheduling, use a real server cron job.
     */
    public function handleCron(Event $event): void
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        // Get enabled tasks.
        $cronSettings = $settings->get('cron', []);
        $enabledTasks = [];
        foreach ($cronSettings['tasks'] ?? [] as $taskId => $taskSettings) {
            if (!empty($taskSettings['enabled'])) {
                $enabledTasks[$taskId] = $taskSettings;
            }
        }

        if (!count($enabledTasks)) {
            return;
        }

        // Run only the tasks due according to each task's own frequency. The
        // per-task last run naturally throttles: a daily task is not due again
        // for 24h, so no global throttle is needed here.
        $now = time();
        $lastRun = $settings->get('cron_task_last', []);
        $due = \Cron\Job\CronTasks::filterDueTasks($enabledTasks, is_array($lastRun) ? $lastRun : [], $now);
        if (!count($due)) {
            return;
        }

        // Stamp now, so concurrent page loads do not dispatch duplicate jobs.
        foreach (array_keys($due) as $taskId) {
            $lastRun[$taskId] = $now;
        }
        $settings->set('cron_task_last', $lastRun);
        $settings->set('cron_last', $now);

        // Dispatch the due tasks to the background job (does not block the
        // page).
        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
        $dispatcher->dispatch(\Cron\Job\CronTasks::class, [
            'tasks' => $due,
            'manual' => false,
        ]);
    }
}
