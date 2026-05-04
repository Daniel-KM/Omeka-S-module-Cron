<?php declare(strict_types=1);

namespace CronTest\Service\Form;

use CommonTest\AbstractHttpControllerTestCase;
use Cron\Form\CronForm;
use Cron\Service\Form\CronFormFactory;
use CronTest\CronTestTrait;

/**
 * Tests for the CronFormFactory.
 */
class CronFormFactoryTest extends AbstractHttpControllerTestCase
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
     * Test factory creates CronForm instance.
     */
    public function testFactoryCreatesCronForm(): void
    {
        $factory = new CronFormFactory();
        $form = $factory($this->getServiceLocator(), CronForm::class);

        $this->assertInstanceOf(CronForm::class, $form);
    }

    /**
     * Test factory injects cron_tasks config.
     */
    public function testFactoryInjectsCronTasksConfig(): void
    {
        $factory = new CronFormFactory();
        $form = $factory($this->getServiceLocator(), CronForm::class);
        $form->init();

        // The factory should have called setCronTasksConfig with the merged
        // config. Even if no modules registered tasks, it's an array.
        $tasks = $form->getRegisteredTasks();
        $this->assertIsArray($tasks);
    }

    /**
     * Test form obtained via FormElementManager has config injected.
     */
    public function testFormElementManagerUsesFactory(): void
    {
        $formManager = $this->getServiceLocator()->get('FormElementManager');
        $form = $formManager->get(CronForm::class);

        $this->assertInstanceOf(CronForm::class, $form);

        // Init should work without errors (config was injected by factory).
        $form->init();
        $this->assertIsArray($form->getRegisteredTasks());
    }
}
