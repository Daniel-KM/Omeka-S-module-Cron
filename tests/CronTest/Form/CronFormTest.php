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
        $this->assertEquals('cron-form', $form->getAttribute('id'));
    }

    /**
     * Test form has the folder for the backup of "files/", not required.
     */
    public function testFormHasBackupFilesDirField(): void
    {
        $form = $this->getInitializedForm();

        $this->assertTrue($form->has('cron_backup_files_dir'));
        $this->assertInstanceOf(\Laminas\Form\Element\Text::class, $form->get('cron_backup_files_dir'));
        $this->assertFalse($form->getInputFilter()->get('cron_backup_files_dir')->isRequired());
    }

    /**
     * Test the default backup folder was removed in version 3.4.4.
     */
    public function testFormHasNoDefaultBackupDirField(): void
    {
        $this->assertFalse($this->getInitializedForm()->has('cron_backup_dir'));
    }

    /**
     * Test the per-task configuration is not in the form, but in the view.
     */
    public function testFormHasNoTaskField(): void
    {
        $form = $this->getInitializedForm();
        $this->assertFalse($form->has('cron_tasks'));
        $this->assertFalse($form->has('cron_frequency'));
    }

    /**
     * Test the form is valid with an empty folder.
     */
    public function testFormIsValidWithoutFolder(): void
    {
        $form = $this->getInitializedForm();
        $form->remove('csrf');
        $form->getInputFilter()->remove('csrf');
        $form->setData(['cron_backup_files_dir' => '']);
        $this->assertTrue($form->isValid());
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
     * Test setCronTasksConfig returns self (fluent interface).
     */
    public function testSetCronTasksConfigIsFluent(): void
    {
        $form = new CronForm();
        $result = $form->setCronTasksConfig([]);

        $this->assertSame($form, $result);
    }
}
