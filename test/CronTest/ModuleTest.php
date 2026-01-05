<?php declare(strict_types=1);

namespace CronTest;

use Cron\Module;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for Cron Module.
 */
class ModuleTest extends AbstractHttpControllerTestCase
{
    use CronTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }

    /**
     * Test module is installed.
     */
    public function testModuleIsInstalled(): void
    {
        $moduleManager = $this->getService('Omeka\\ModuleManager');
        $module = $moduleManager->getModule('Cron');

        $this->assertNotNull($module);
        $this->assertEquals(
            \Omeka\Module\Manager::STATE_ACTIVE,
            $module->getState()
        );
    }

    /**
     * Test module class exists.
     */
    public function testModuleClassExists(): void
    {
        $this->assertTrue(class_exists(Module::class));
    }

    /**
     * Test module has getConfig method.
     */
    public function testModuleHasGetConfigMethod(): void
    {
        $module = new Module();
        $config = $module->getConfig();

        $this->assertIsArray($config);
    }

    /**
     * Test module config has required keys.
     */
    public function testModuleConfigHasRequiredKeys(): void
    {
        $module = new Module();
        $config = $module->getConfig();

        $this->assertArrayHasKey('controllers', $config);
        $this->assertArrayHasKey('router', $config);
    }

    /**
     * Test CronForm is registered.
     */
    public function testCronFormIsRegistered(): void
    {
        $formElementManager = $this->getService('FormElementManager');

        $this->assertTrue(
            $formElementManager->has(\Cron\Form\CronForm::class)
        );
    }

    /**
     * Test CronController is registered.
     */
    public function testCronControllerIsRegistered(): void
    {
        $controllerManager = $this->getService('ControllerManager');

        $this->assertTrue(
            $controllerManager->has(\Cron\Controller\Admin\CronController::class)
        );
    }

    /**
     * Test module attaches event listeners.
     */
    public function testModuleAttachesEventListeners(): void
    {
        $module = new Module();

        // The module should have the attachListeners method.
        $this->assertTrue(method_exists($module, 'attachListeners'));
    }

    /**
     * Test router has cron routes.
     */
    public function testRouterHasCronRoutes(): void
    {
        $module = new Module();
        $config = $module->getConfig();

        $this->assertArrayHasKey('router', $config);
        $this->assertArrayHasKey('routes', $config['router']);
        $this->assertArrayHasKey('admin', $config['router']['routes']);
        $this->assertArrayHasKey('child_routes', $config['router']['routes']['admin']);
        $this->assertArrayHasKey('cron', $config['router']['routes']['admin']['child_routes']);
    }
}
