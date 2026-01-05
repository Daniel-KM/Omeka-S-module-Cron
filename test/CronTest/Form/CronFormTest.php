<?php declare(strict_types=1);

namespace CronTest\Form;

use Cron\Form\CronForm;
use CronTest\CronTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for Cron Form.
 */
class CronFormTest extends AbstractHttpControllerTestCase
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
     * Test form can be instantiated.
     */
    public function testFormInstantiation(): void
    {
        $form = $this->getCronForm();
        $this->assertInstanceOf(CronForm::class, $form);
    }

    /**
     * Test form has required elements.
     */
    public function testFormHasRequiredElements(): void
    {
        $form = $this->getCronForm();

        $this->assertTrue($form->has('cron_tasks'));
        $this->assertTrue($form->has('cron_frequency'));
    }

    /**
     * Test frequency options are available.
     */
    public function testFrequencyOptions(): void
    {
        $form = $this->getCronForm();
        $frequencyElement = $form->get('cron_frequency');

        $options = $frequencyElement->getValueOptions();

        $this->assertArrayHasKey('hourly', $options);
        $this->assertArrayHasKey('daily', $options);
        $this->assertArrayHasKey('weekly', $options);
        $this->assertArrayHasKey('monthly', $options);
    }

    /**
     * Test form triggers cron.tasks event.
     */
    public function testFormTriggersCronTasksEvent(): void
    {
        $formElementManager = $this->getService('FormElementManager');
        $form = $formElementManager->get(CronForm::class);

        // After init, registeredTasks should be collected (even if empty).
        $tasks = $form->getRegisteredTasks();
        $this->assertIsArray($tasks);
    }

    /**
     * Test prepareSettingsFromData conversion.
     */
    public function testPrepareSettingsFromData(): void
    {
        $form = $this->getCronForm();

        $data = [
            'cron_tasks' => ['test_task'],
            'cron_frequency' => 'weekly',
        ];

        $settings = $form->prepareSettingsFromData($data);

        $this->assertArrayHasKey('global_frequency', $settings);
        $this->assertEquals('weekly', $settings['global_frequency']);
        $this->assertArrayHasKey('tasks', $settings);
    }

    /**
     * Test prepareDataFromSettings conversion.
     */
    public function testPrepareDataFromSettings(): void
    {
        $form = $this->getCronForm();

        $settings = [
            'tasks' => [
                'test_task' => ['enabled' => true, 'frequency' => 'daily'],
            ],
            'global_frequency' => 'daily',
        ];

        $data = $form->prepareDataFromSettings($settings);

        $this->assertArrayHasKey('cron_frequency', $data);
        $this->assertEquals('daily', $data['cron_frequency']);
        $this->assertArrayHasKey('cron_tasks', $data);
        $this->assertContains('test_task', $data['cron_tasks']);
    }

    /**
     * Test default frequency is daily.
     */
    public function testDefaultFrequencyIsDaily(): void
    {
        $form = $this->getCronForm();
        $frequencyElement = $form->get('cron_frequency');

        $this->assertEquals('daily', $frequencyElement->getValue());
    }

    /**
     * Test form has form id attribute.
     */
    public function testFormHasIdAttribute(): void
    {
        $form = $this->getCronForm();
        $this->assertEquals('form-cron', $form->getAttribute('id'));
    }
}
