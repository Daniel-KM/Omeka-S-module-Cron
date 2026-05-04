<?php declare(strict_types=1);

namespace CronTest\Controller\Admin;

use CommonTest\AbstractHttpControllerTestCase;
use Cron\Controller\Admin\CronController;
use CronTest\CronTestTrait;

/**
 * Tests for the Cron admin controller.
 */
class CronControllerTest extends AbstractHttpControllerTestCase
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
     * Test controller extends AbstractActionController.
     */
    public function testControllerExtendsAbstractActionController(): void
    {
        $reflection = new \ReflectionClass(CronController::class);
        $this->assertTrue(
            $reflection->isSubclassOf(\Laminas\Mvc\Controller\AbstractActionController::class)
        );
    }

    /**
     * Test controller has indexAction.
     */
    public function testControllerHasIndexAction(): void
    {
        $this->assertTrue(method_exists(CronController::class, 'indexAction'));
    }

    /**
     * Test controller can be instantiated from manager.
     */
    public function testControllerCanBeInstantiated(): void
    {
        $controllerManager = $this->getServiceLocator()->get('ControllerManager');
        $controller = $controllerManager->get(CronController::class);

        $this->assertInstanceOf(CronController::class, $controller);
    }

    /**
     * Test index page is accessible with GET.
     */
    public function testIndexActionIsAccessible(): void
    {
        $this->dispatch('/admin/cron');

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(CronController::class);
        $this->assertActionName('index');
    }

    /**
     * Test index page renders the form.
     */
    public function testIndexPageRendersForm(): void
    {
        $this->dispatch('/admin/cron');

        $this->assertResponseStatusCode(200);
        $this->assertQuery('form#form-cron');
    }

    /**
     * Test index page renders frequency radio options.
     */
    public function testIndexPageRendersFrequencyOptions(): void
    {
        $this->dispatch('/admin/cron');

        $this->assertResponseStatusCode(200);
        $this->assertQuery('input[name="cron_frequency"]');
    }

    /**
     * Test index page shows cron command section.
     */
    public function testIndexPageShowsCronCommandSection(): void
    {
        $this->dispatch('/admin/cron');

        $this->assertResponseStatusCode(200);
        $this->assertQuery('.cron-command');
    }

    /**
     * Test index page shows status section.
     */
    public function testIndexPageShowsStatusSection(): void
    {
        $this->dispatch('/admin/cron');

        $this->assertResponseStatusCode(200);
        $this->assertQuery('.cron-info');
    }

    /**
     * Test index page shows save button.
     */
    public function testIndexPageShowsSaveButton(): void
    {
        $this->dispatch('/admin/cron');

        $this->assertResponseStatusCode(200);
        $this->assertQuery('button[type="submit"]');
    }

    /**
     * Test POST without CSRF re-displays form (validation fails).
     */
    public function testPostWithoutCsrfReDisplaysForm(): void
    {
        $this->dispatch('/admin/cron', 'POST', [
            'cron_frequency' => 'weekly',
            'cron_tasks' => [],
        ]);

        // Without CSRF token, form validation fails → 200 (re-displayed).
        $this->assertResponseStatusCode(200);
    }

    /**
     * Test POST "run now" with no enabled tasks redirects.
     */
    public function testPostRunNowWithNoEnabledTasksRedirects(): void
    {
        $this->settings()->set('cron', [
            'tasks' => [],
            'global_frequency' => 'daily',
        ]);

        $this->dispatch('/admin/cron', 'POST', [
            'run_now' => '1',
        ]);

        $this->assertResponseStatusCode(302);
    }
}
