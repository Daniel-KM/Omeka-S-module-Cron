<?php declare(strict_types=1);

namespace CronTest\Form;

use CommonTest\AbstractHttpControllerTestCase;
use Cron\Form\CronForm;
use CronTest\CronTestTrait;

/**
 * Tests for the Cron configuration form.
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
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * Get an initialized form instance via FormElementManager.
     */
    protected function getInitializedForm(): CronForm
    {
        $formManager = $this->getServiceLocator()->get('FormElementManager');
        $form = $formManager->get(CronForm::class);
        $form->init();
        return $form;
    }

    /**
     * Test form can be instantiated and initialized.
     */
    public function testFormCanBeInitialized(): void
    {
        $form = $this->getInitializedForm();
        $this->assertInstanceOf(CronForm::class, $form);
    }

    /**
     * Test form has id attribute.
     */
    public function testFormHasIdAttribute(): void
    {
        $form = $this->getInitializedForm();
        $this->assertEquals('form-cron', $form->getAttribute('id'));
    }

    /**
     * Test form has cron_tasks multi-checkbox field.
     */
    public function testFormHasCronTasksField(): void
    {
        $form = $this->getInitializedForm();

        $this->assertTrue($form->has('cron_tasks'));
        $element = $form->get('cron_tasks');
        $this->assertInstanceOf(\Laminas\Form\Element\MultiCheckbox::class, $element);
    }

    /**
     * Test form has cron_frequency radio field.
     */
    public function testFormHasCronFrequencyField(): void
    {
        $form = $this->getInitializedForm();

        $this->assertTrue($form->has('cron_frequency'));
        $element = $form->get('cron_frequency');
        $this->assertInstanceOf(\Laminas\Form\Element\Radio::class, $element);
    }

    /**
     * Test frequency radio has all expected options.
     */
    public function testFrequencyHasAllOptions(): void
    {
        $form = $this->getInitializedForm();
        $element = $form->get('cron_frequency');
        $options = $element->getValueOptions();

        $this->assertArrayHasKey('hourly', $options);
        $this->assertArrayHasKey('daily', $options);
        $this->assertArrayHasKey('weekly', $options);
        $this->assertArrayHasKey('monthly', $options);
    }

    /**
     * Test default frequency is daily.
     */
    public function testDefaultFrequencyIsDaily(): void
    {
        $form = $this->getInitializedForm();
        $element = $form->get('cron_frequency');

        $this->assertEquals('daily', $element->getValue());
    }

    /**
     * Test cron_tasks is not required.
     */
    public function testCronTasksIsNotRequired(): void
    {
        $form = $this->getInitializedForm();
        $inputFilter = $form->getInputFilter();
        $input = $inputFilter->get('cron_tasks');

        $this->assertFalse($input->isRequired());
    }

    /**
     * Test cron_frequency is not required.
     */
    public function testCronFrequencyIsNotRequired(): void
    {
        $form = $this->getInitializedForm();
        $inputFilter = $form->getInputFilter();
        $input = $inputFilter->get('cron_frequency');

        $this->assertFalse($input->isRequired());
    }

    /**
     * Test getRegisteredTasks returns array.
     */
    public function testGetRegisteredTasksReturnsArray(): void
    {
        $form = $this->getInitializedForm();
        $tasks = $form->getRegisteredTasks();

        $this->assertIsArray($tasks);
    }

    /**
     * Test setCronTasksConfig and task collection.
     */
    public function testSetCronTasksConfigAndCollection(): void
    {
        $form = new CronForm();
        $tasks = [
            'test_task' => [
                'label' => 'Test task',
                'module' => 'TestModule',
            ],
        ];
        $form->setCronTasksConfig($tasks);
        $form->init();

        $registered = $form->getRegisteredTasks();
        $this->assertArrayHasKey('test_task', $registered);
        $this->assertEquals('Test task', $registered['test_task']['label']);
    }

    /**
     * Test task options are built from config.
     */
    public function testTaskOptionsBuiltFromConfig(): void
    {
        $form = new CronForm();
        $form->setCronTasksConfig([
            'task_a' => [
                'label' => 'Task A',
                'module' => 'ModA',
            ],
            'task_b' => [
                'label' => 'Task B',
                'module' => 'ModB',
            ],
        ]);
        $form->init();

        $element = $form->get('cron_tasks');
        $options = $element->getValueOptions();

        $this->assertArrayHasKey('task_a', $options);
        $this->assertArrayHasKey('task_b', $options);
        $this->assertStringContainsString('[ModA]', $options['task_a']);
        $this->assertStringContainsString('Task B', $options['task_b']);
    }

    /**
     * Test task with sub-options expands into separate checkboxes.
     */
    public function testTaskWithSubOptionsExpandsIntoCheckboxes(): void
    {
        $form = new CronForm();
        $form->setCronTasksConfig([
            'parent_task' => [
                'label' => 'Parent',
                'module' => 'Mod',
                'options' => [
                    'sub_a' => 'Sub-option A',
                    'sub_b' => 'Sub-option B',
                ],
            ],
        ]);
        $form->init();

        $element = $form->get('cron_tasks');
        $options = $element->getValueOptions();

        $this->assertArrayNotHasKey('parent_task', $options);
        $this->assertArrayHasKey('sub_a', $options);
        $this->assertArrayHasKey('sub_b', $options);
        $this->assertStringContainsString('Sub-option A', $options['sub_a']);
    }

    /**
     * Test prepareSettingsFromData with simple tasks.
     */
    public function testPrepareSettingsFromDataSimple(): void
    {
        $form = new CronForm();
        $form->setCronTasksConfig([
            'task_a' => ['label' => 'A', 'module' => 'M'],
            'task_b' => ['label' => 'B', 'module' => 'M'],
        ]);
        $form->init();

        $settings = $form->prepareSettingsFromData([
            'cron_tasks' => ['task_a'],
            'cron_frequency' => 'weekly',
        ]);

        $this->assertEquals('weekly', $settings['global_frequency']);
        $this->assertArrayHasKey('task_a', $settings['tasks']);
        $this->assertTrue($settings['tasks']['task_a']['enabled']);
        $this->assertEquals('weekly', $settings['tasks']['task_a']['frequency']);
        $this->assertArrayNotHasKey('task_b', $settings['tasks']);
    }

    /**
     * Test prepareSettingsFromData with sub-options.
     */
    public function testPrepareSettingsFromDataWithSubOptions(): void
    {
        $form = new CronForm();
        $form->setCronTasksConfig([
            'parent' => [
                'label' => 'Parent',
                'module' => 'M',
                'options' => [
                    'sub_a' => 'A',
                    'sub_b' => 'B',
                ],
            ],
        ]);
        $form->init();

        $settings = $form->prepareSettingsFromData([
            'cron_tasks' => ['sub_a'],
            'cron_frequency' => 'hourly',
        ]);

        $this->assertArrayHasKey('sub_a', $settings['tasks']);
        $this->assertEquals('parent', $settings['tasks']['sub_a']['parent_task']);
        $this->assertArrayNotHasKey('sub_b', $settings['tasks']);
    }

    /**
     * Test prepareDataFromSettings extracts enabled tasks.
     */
    public function testPrepareDataFromSettings(): void
    {
        $form = $this->getInitializedForm();

        $data = $form->prepareDataFromSettings([
            'tasks' => [
                'task_a' => ['enabled' => true, 'frequency' => 'daily'],
                'task_b' => ['enabled' => false, 'frequency' => 'daily'],
                'task_c' => ['enabled' => true, 'frequency' => 'weekly'],
            ],
            'global_frequency' => 'weekly',
        ]);

        $this->assertEquals('weekly', $data['cron_frequency']);
        $this->assertContains('task_a', $data['cron_tasks']);
        $this->assertContains('task_c', $data['cron_tasks']);
        $this->assertNotContains('task_b', $data['cron_tasks']);
    }

    /**
     * Test prepareDataFromSettings with empty settings.
     */
    public function testPrepareDataFromSettingsEmpty(): void
    {
        $form = $this->getInitializedForm();

        $data = $form->prepareDataFromSettings([]);

        $this->assertEquals('daily', $data['cron_frequency']);
        $this->assertEmpty($data['cron_tasks']);
    }

    /**
     * Test setCronTasksConfig returns self (fluent interface).
     */
    public function testSetCronTasksConfigIsFluent(): void
    {
        $form = new CronForm();
        $result = $form->setCronTasksConfig([]);

        $this->assertSame($form, $result);
    }
}
