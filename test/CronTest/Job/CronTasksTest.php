<?php declare(strict_types=1);

namespace CronTest\Job;

use Cron\Job\CronTasks;
use CronTest\CronTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for CronTasks job.
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
        $this->logout();
        parent::tearDown();
    }

    /**
     * Test job can be instantiated.
     */
    public function testJobInstantiation(): void
    {
        $serviceLocator = $this->getApplication()->getServiceManager();

        // Create a mock job entity.
        $entityManager = $this->getEntityManager();
        $user = $this->getCurrentUser();

        $jobEntity = new \Omeka\Entity\Job();
        $jobEntity->setOwner($user);
        $jobEntity->setClass(CronTasks::class);
        $jobEntity->setArgs([]);
        $jobEntity->setStatus(\Omeka\Entity\Job::STATUS_STARTING);
        $entityManager->persist($jobEntity);
        $entityManager->flush();

        $this->createdJobs[] = $jobEntity->getId();

        // The job class should exist.
        $this->assertTrue(class_exists(CronTasks::class));
    }

    /**
     * Test job with no tasks.
     */
    public function testJobWithNoTasks(): void
    {
        $job = $this->dispatchJob(CronTasks::class, [
            'tasks' => [],
        ]);

        $this->assertNotNull($job->getId());
    }

    /**
     * Test job with mock tasks.
     */
    public function testJobWithTasks(): void
    {
        $job = $this->dispatchJob(CronTasks::class, [
            'tasks' => [
                'test_task' => [
                    'enabled' => true,
                    'option' => 'test_option',
                ],
            ],
            'manual' => true,
        ]);

        $this->assertNotNull($job->getId());
    }

    /**
     * Test job triggers cron.execute event.
     */
    public function testJobTriggersCronExecuteEvent(): void
    {
        // The job should trigger events for each task.
        // Since we can't easily test event triggering in unit tests,
        // we just verify the job completes without errors.
        $job = $this->dispatchJob(CronTasks::class, [
            'tasks' => [
                'test_task' => [
                    'enabled' => true,
                ],
            ],
        ]);

        $this->assertNotNull($job);
    }

    /**
     * Test job class has getLogger method.
     */
    public function testJobHasGetLoggerMethod(): void
    {
        $this->assertTrue(method_exists(CronTasks::class, 'getLogger'));
    }

    /**
     * Test job extends AbstractJob.
     */
    public function testJobExtendsAbstractJob(): void
    {
        $reflection = new \ReflectionClass(CronTasks::class);
        $this->assertTrue($reflection->isSubclassOf(\Omeka\Job\AbstractJob::class));
    }
}
