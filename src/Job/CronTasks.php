<?php declare(strict_types=1);

namespace Cron\Job;

use Laminas\EventManager\Event;
use Omeka\Job\AbstractJob;

/**
 * Execute scheduled cron tasks.
 *
 * This job runs all enabled tasks configured in the cron settings.
 * Tasks are registered by modules via the 'cron.tasks' event on CronForm.
 *
 * Modules handle their task execution via the 'cron.execute' event.
 */
class CronTasks extends AbstractJob
{
    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    public function perform(): void
    {
        $services = $this->getServiceLocator();

        // Set up logger with reference.
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('cron/job_' . $this->job->getId());

        $this->logger = $services->get('Omeka\Logger');
        $this->logger->addProcessor($referenceIdProcessor);

        $tasks = $this->getArg('tasks', []);
        $isManual = $this->getArg('manual', false);

        if (!count($tasks)) {
            $this->logger->notice('No tasks to execute.'); // @translate
            return;
        }

        $this->logger->notice(
            'Starting cron execution with {count} tasks.', // @translate
            ['count' => count($tasks)]
        );

        $executedCount = 0;
        $errorCount = 0;

        foreach ($tasks as $taskId => $taskSettings) {
            if ($this->shouldStop()) {
                $this->logger->warn('Job stopped by user.'); // @translate
                break;
            }

            try {
                $this->executeTask($taskId, $taskSettings);
                $executedCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $this->logger->err(
                    'Error executing task "{task}": {error}', // @translate
                    ['task' => $taskId, 'error' => $e->getMessage()]
                );
            }
        }

        $this->logger->notice(
            'Cron execution completed: {executed} tasks executed, {errors} errors.', // @translate
            ['executed' => $executedCount, 'errors' => $errorCount]
        );
    }

    /**
     * Execute a single task.
     *
     * Triggers the 'cron.execute' event to let modules handle their tasks.
     */
    protected function executeTask(string $taskId, array $taskSettings): void
    {
        $this->logger->info(
            'Executing task "{task}".', // @translate
            ['task' => $taskId]
        );

        $services = $this->getServiceLocator();
        $eventManager = $services->get('EventManager');

        $event = new Event('cron.execute', $this, [
            'task_id' => $taskId,
            'task_settings' => $taskSettings,
            'logger' => $this->logger,
            'handled' => false,
        ]);

        // Allow modules to handle their tasks.
        $eventManager->setIdentifiers([self::class, 'Cron\Job\CronTasks']);

        // Trigger via shared event manager so modules can listen.
        $sharedEvents = $eventManager->getSharedManager();
        $sharedEvents->attach(
            'Cron\Job\CronTasks',
            'cron.execute',
            function ($e): void {
                // This is just to ensure the event is triggered.
            },
            1
        );

        $eventManager->triggerEvent($event);

        if (!$event->getParam('handled')) {
            $this->logger->warn(
                'Task "{task}" has no handler.', // @translate
                ['task' => $taskId]
            );
        }
    }

    /**
     * Get the logger for use by task handlers.
     */
    public function getLogger(): \Laminas\Log\Logger
    {
        return $this->logger;
    }
}
