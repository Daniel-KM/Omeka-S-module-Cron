<?php declare(strict_types=1);

namespace CronTest\Controller\Admin;

use Cron\Controller\Admin\CronController;
use CronTest\CronTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for Cron Controller.
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
        $this->logout();
        parent::tearDown();
    }

    /**
     * Test cron controller class exists.
     */
    public function testCronControllerClassExists(): void
    {
        $this->assertTrue(class_exists(CronController::class));
    }

    /**
     * Test cron controller has index action.
     */
    public function testCronControllerHasIndexAction(): void
    {
        $this->assertTrue(method_exists(CronController::class, 'indexAction'));
    }

    /**
     * Test cron controller is registered.
     */
    public function testCronControllerIsRegistered(): void
    {
        $controllerManager = $this->getService('ControllerManager');

        $this->assertTrue(
            $controllerManager->has(CronController::class)
        );
    }

    /**
     * Test cron controller can be instantiated.
     */
    public function testCronControllerCanBeInstantiated(): void
    {
        $controllerManager = $this->getService('ControllerManager');
        $controller = $controllerManager->get(CronController::class);

        $this->assertInstanceOf(CronController::class, $controller);
    }

    /**
     * Test cron controller extends AbstractActionController.
     */
    public function testCronControllerExtendsAbstractActionController(): void
    {
        $reflection = new \ReflectionClass(CronController::class);
        $this->assertTrue($reflection->isSubclassOf(\Laminas\Mvc\Controller\AbstractActionController::class));
    }
}
