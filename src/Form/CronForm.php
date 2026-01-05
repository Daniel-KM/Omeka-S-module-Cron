<?php declare(strict_types=1);

namespace Cron\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

/**
 * Cron configuration form.
 *
 * Tasks are registered via 'cron_tasks' key in module.config.php.
 * Each task defines:
 * - id: unique task identifier (the array key)
 * - label: display name
 * - module: source module name
 * - job: job class to dispatch (optional)
 * - frequencies: supported frequencies ['hourly', 'daily', 'weekly', 'monthly'] (optional)
 * - default_frequency: default frequency (optional, defaults to 'daily')
 * - options: sub-options for configurable tasks (optional)
 *
 * Settings are stored as:
 * [
 *     'tasks' => ['task_id' => ['enabled' => true, 'frequency' => 'daily'], ...],
 *     'global_frequency' => 'daily',
 * ]
 */
class CronForm extends Form
{
    /**
     * @var array Registered cron tasks from modules config
     */
    protected $registeredTasks = [];

    /**
     * @var array Cron tasks from merged module config
     */
    protected $cronTasksConfig = [];

    /**
     * Set cron tasks from config.
     *
     * Called by the factory with merged config from all modules.
     */
    public function setCronTasksConfig(array $cronTasksConfig): self
    {
        $this->cronTasksConfig = $cronTasksConfig;
        return $this;
    }

    public function init(): void
    {
        $this->setAttribute('id', 'form-cron');

        // Collect tasks from config.
        $this->collectTasks();

        // Build task checkboxes.
        $taskOptions = $this->buildTaskOptions();

        $this
            ->add([
                'name' => 'cron_tasks',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Scheduled tasks', // @translate
                    'label_attributes' => ['style' => 'display: inline-block'],
                    'value_options' => $taskOptions,
                ],
                'attributes' => [
                    'id' => 'cron_tasks',
                ],
            ])
            ->add([
                'name' => 'cron_frequency',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Frequency', // @translate
                    'value_options' => [
                        'hourly' => 'Hourly', // @translate
                        'daily' => 'Daily (recommended)', // @translate
                        'weekly' => 'Weekly', // @translate
                        'monthly' => 'Monthly', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'cron_frequency',
                    'value' => 'daily',
                ],
            ])
        ;

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'cron_tasks',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'cron_frequency',
            'required' => false,
        ]);
    }

    /**
     * Collect cron tasks from merged module config.
     *
     * Modules register their tasks in their module.config.php under
     * the 'cron_tasks' key. Example:
     *
     * // In module.config.php
     * return [
     *     'cron_tasks' => [
     *         'my_task' => [
     *             'label' => 'My task description',
     *             'module' => 'MyModule',
     *             'job' => \MyModule\Job\MyJob::class,
     *             'frequencies' => ['hourly', 'daily'],
     *             'default_frequency' => 'daily',
     *         ],
     *     ],
     * ];
     */
    protected function collectTasks(): void
    {
        $this->registeredTasks = $this->cronTasksConfig;
    }

    /**
     * Build value options for the task checkboxes.
     */
    protected function buildTaskOptions(): array
    {
        $options = [];

        foreach ($this->registeredTasks as $taskId => $task) {
            $module = $task['module'] ?? 'Unknown';
            $label = $task['label'] ?? $taskId;

            // For tasks with sub-options (like session cleanup).
            if (!empty($task['options'])) {
                foreach ($task['options'] as $optionId => $optionLabel) {
                    $options[$optionId] = sprintf('[%s] %s (%s)', $module, $label, $optionLabel);
                }
            } else {
                $options[$taskId] = sprintf('[%s] %s', $module, $label);
            }
        }

        return $options;
    }

    /**
     * Get all registered tasks.
     */
    public function getRegisteredTasks(): array
    {
        return $this->registeredTasks;
    }

    /**
     * Convert form data to settings structure.
     */
    public function prepareSettingsFromData(array $data): array
    {
        $settings = [
            'tasks' => [],
            'global_frequency' => $data['cron_frequency'] ?? 'daily',
        ];

        $enabledTasks = $data['cron_tasks'] ?? [];
        foreach ($this->registeredTasks as $taskId => $task) {
            // Handle tasks with sub-options.
            if (!empty($task['options'])) {
                foreach ($task['options'] as $optionId => $optionLabel) {
                    if (in_array($optionId, $enabledTasks)) {
                        $settings['tasks'][$optionId] = [
                            'enabled' => true,
                            'frequency' => $data['cron_frequency'] ?? $task['default_frequency'] ?? 'daily',
                            'parent_task' => $taskId,
                        ];
                    }
                }
            } else {
                if (in_array($taskId, $enabledTasks)) {
                    $settings['tasks'][$taskId] = [
                        'enabled' => true,
                        'frequency' => $data['cron_frequency'] ?? $task['default_frequency'] ?? 'daily',
                    ];
                }
            }
        }

        return $settings;
    }

    /**
     * Convert settings structure to form data.
     */
    public function prepareDataFromSettings(array $settings): array
    {
        $data = [
            'cron_tasks' => [],
            'cron_frequency' => $settings['global_frequency'] ?? 'daily',
        ];

        foreach ($settings['tasks'] ?? [] as $taskId => $taskSettings) {
            if (!empty($taskSettings['enabled'])) {
                $data['cron_tasks'][] = $taskId;
            }
        }

        return $data;
    }
}
