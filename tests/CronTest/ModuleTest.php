<?php declare(strict_types=1);

namespace CronTest;

use CommonTest\AbstractHttpControllerTestCase;
use Cron\Module;

/**
 * Tests for the Cron module bootstrap.
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
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * Test module is active.
     */
    public function testModuleIsActive(): void
    {
        $moduleManager = $this->getServiceLocator()->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('Cron');

        $this->assertNotNull($module);
        $this->assertEquals(
            \Omeka\Module\Manager::STATE_ACTIVE,
            $module->getState()
        );
    }

    /**
     * Test module config has expected keys.
     */
    public function testModuleConfigHasExpectedKeys(): void
    {
        $module = new Module();
        $config = $module->getConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('form_elements', $config);
        $this->assertArrayHasKey('controllers', $config);
        $this->assertArrayHasKey('router', $config);
        $this->assertArrayHasKey('cron', $config);
        $this->assertArrayHasKey('cron_tasks', $config);
    }

    /**
     * Test CronForm is registered in form element manager.
     */
    public function testCronFormIsRegistered(): void
    {
        $formManager = $this->getServiceLocator()->get('FormElementManager');
        $this->assertTrue($formManager->has(\Cron\Form\CronForm::class));
    }

    /**
     * Test CronController is registered in controller manager.
     */
    public function testCronControllerIsRegistered(): void
    {
        $controllerManager = $this->getServiceLocator()->get('ControllerManager');
        $this->assertTrue(
            $controllerManager->has(\Cron\Controller\Admin\CronController::class)
        );
    }

    /**
     * Test cron route is defined.
     */
    public function testCronRouteIsDefined(): void
    {
        $module = new Module();
        $config = $module->getConfig();

        $routes = $config['router']['routes']['admin']['child_routes'];
        $this->assertArrayHasKey('cron', $routes);
        $this->assertEquals('/cron', $routes['cron']['options']['route']);
    }

    /**
     * Test default cron settings.
     */
    public function testDefaultCronSettings(): void
    {
        $module = new Module();
        $config = $module->getConfig();

        $cronSettings = $config['cron']['settings']['cron'];
        $this->assertArrayHasKey('tasks', $cronSettings);
        $this->assertArrayHasKey('global_frequency', $cronSettings);
        $this->assertEmpty($cronSettings['tasks']);
        $this->assertEquals('daily', $cronSettings['global_frequency']);
    }

    /**
     * Test ACL allows public access to cron controller.
     */
    public function testAclAllowsPublicAccessToCronController(): void
    {
        $acl = $this->getServiceLocator()->get('Omeka\Acl');

        $this->assertTrue(
            $acl->isAllowed(null, \Cron\Controller\Admin\CronController::class)
        );
    }

    /**
     * Test module attaches view.layout listener.
     */
    public function testModuleAttachesViewLayoutListener(): void
    {
        $module = new Module();
        $this->assertTrue(method_exists($module, 'attachListeners'));
        $this->assertTrue(method_exists($module, 'handleCron'));
    }
}
