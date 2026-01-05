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

        // Check frequency based on global setting.
        $frequency = $cronSettings['global_frequency'] ?? 'daily';
        $frequencies = [
            'hourly' => 3600,
            'daily' => 86400,
            'weekly' => 604800,
            'monthly' => 2592000,
            'default' => 86400,
        ];
        $frequencySeconds =$frequencies[$frequency] ?? $frequencies['default'];

        $lastCron = (int) $settings->get('cron_last');
        $time = time();
        if ($lastCron + $frequencySeconds > $time) {
            return;
        }
        $settings->set('cron_last', $time);

        // Dispatch all tasks to the CronTasks job.
        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
        $dispatcher->dispatch(\Cron\Job\CronTasks::class, [
            'tasks' => $enabledTasks,
            'manual' => false,
        ]);
    }
}
