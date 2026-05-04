<?php declare(strict_types=1);

namespace CronTest\Job;

use CommonTest\AbstractHttpControllerTestCase;
use Cron\Job\CronTasks;
use CronTest\CronTestTrait;
use Omeka\Entity\Job;

/**
 * Tests for the CronTasks background job.
 */
class CronTasksTest extends AbstractHttpControllerTestCase
{
    use CronTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * Test job class extends AbstractJob.
     */
    public function testJobExtendsAbstractJob(): void
    {
        $reflection = new \ReflectionClass(CronTasks::class);
        $this->assertTrue($reflection->isSubclassOf(\Omeka\Job\AbstractJob::class));
    }

    /**
     * Test job has getLogger method.
     */
    public function testJobHasGetLoggerMethod(): void
    {
        $this->assertTrue(method_exists(CronTasks::class, 'getLogger'));
    }

    /**
     * Test job with no tasks completes successfully.
     */
    public function testJobWithNoTasks(): void
    {
        $job = $this->runJob(CronTasks::class, [
            'tasks' => [],
        ]);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
    }

    /**
     * Test job with a single unhandled task completes (warns but no error).
     */
    public function testJobWithUnhandledTask(): void
    {
        $job = $this->runJob(CronTasks::class, [
            'tasks' => [
                'nonexistent_task' => [
                    'enabled' => true,
                ],
            ],
            'manual' => true,
        ]);

        // Job completes even if no handler claimed the task.
        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
    }

    /**
     * Test job with multiple tasks processes all of them.
     */
    public function testJobWithMultipleTasks(): void
    {
        $job = $this->runJob(CronTasks::class, [
            'tasks' => [
                'task_a' => ['enabled' => true],
                'task_b' => ['enabled' => true],
                'task_c' => ['enabled' => true],
            ],
        ]);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
    }

    /**
     * Test job reads 'manual' argument.
     */
    public function testJobReadsManualArgument(): void
    {
        // Manual=true should not change behavior in the current implementation
        // but should be accepted without error.
        $job = $this->runJob(CronTasks::class, [
            'tasks' => [],
            'manual' => true,
        ]);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
    }

    /**
     * Test job sets up logger with reference id.
     */
    public function testJobSetsUpLogger(): void
    {
        $job = $this->runJob(CronTasks::class, [
            'tasks' => ['test' => ['enabled' => true]],
        ]);

        // Job completed means logger was set up without error.
        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
        $this->assertNotNull($job->getStarted());
        $this->assertNotNull($job->getEnded());
    }

    /**
     * Test job triggers cron.execute event for each task.
     */
    public function testJobTriggersCronExecuteEvent(): void
    {
        $eventTriggered = false;
        $receivedTaskId = null;

        $services = $this->getServiceLocator();
        $sharedEvents = $services->get('SharedEventManager');
        $sharedEvents->attach(
            'Cron\Job\CronTasks',
            'cron.execute',
            function ($event) use (&$eventTriggered, &$receivedTaskId): void {
                $eventTriggered = true;
                $receivedTaskId = $event->getParam('task_id');
                $event->setParam('handled', true);
            },
            100
        );

        $job = $this->runJob(CronTasks::class, [
            'tasks' => [
                'my_test_task' => ['enabled' => true],
            ],
        ]);

        $this->assertTrue($eventTriggered, 'cron.execute event was not triggered');
        $this->assertEquals('my_test_task', $receivedTaskId);
        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
    }

    /**
     * Test cron.execute event receives task_settings parameter.
     */
    public function testCronExecuteEventReceivesTaskSettings(): void
    {
        $receivedSettings = null;

        $services = $this->getServiceLocator();
        $sharedEvents = $services->get('SharedEventManager');
        $sharedEvents->attach(
            'Cron\Job\CronTasks',
            'cron.execute',
            function ($event) use (&$receivedSettings): void {
                $receivedSettings = $event->getParam('task_settings');
                $event->setParam('handled', true);
            },
            100
        );

        $taskSettings = [
            'enabled' => true,
            'frequency' => 'hourly',
            'custom_option' => 'value',
        ];

        $this->runJob(CronTasks::class, [
            'tasks' => [
                'settings_test' => $taskSettings,
            ],
        ]);

        $this->assertIsArray($receivedSettings);
        $this->assertEquals('value', $receivedSettings['custom_option']);
    }

    /**
     * Test cron.execute event receives logger parameter.
     */
    public function testCronExecuteEventReceivesLogger(): void
    {
        $receivedLogger = null;

        $services = $this->getServiceLocator();
        $sharedEvents = $services->get('SharedEventManager');
        $sharedEvents->attach(
            'Cron\Job\CronTasks',
            'cron.execute',
            function ($event) use (&$receivedLogger): void {
                $receivedLogger = $event->getParam('logger');
                $event->setParam('handled', true);
            },
            100
        );

        $this->runJob(CronTasks::class, [
            'tasks' => ['logger_test' => ['enabled' => true]],
        ]);

        $this->assertInstanceOf(\Laminas\Log\Logger::class, $receivedLogger);
    }
}
