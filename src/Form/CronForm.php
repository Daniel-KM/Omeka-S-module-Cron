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
            ->add([
                'name' => 'cron_backup_dir',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Default backup folder', // @translate
                    'info' => 'Absolute path where backups are stored by default. Leave empty for "files/backup". The folder must be writable.', // @translate
                ],
                'attributes' => [
                    'id' => 'cron_backup_dir',
                    'placeholder' => '/path/to/backup', // @translate
                ],
            ])
            ->add([
                'name' => 'cron_backup_files_dir',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Folder for the "files/" backup', // @translate
                    'info' => 'Absolute path where the "files/" directory is backed up. Leave empty to use the default backup folder. It must be writable and outside "files/" (not a sub-folder, else the backup would archive itself).', // @translate
                ],
                'attributes' => [
                    'id' => 'cron_backup_files_dir',
                    'placeholder' => '/path/to/files-backup', // @translate
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
        $inputFilter->add([
            'name' => 'cron_backup_dir',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'cron_backup_files_dir',
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

        // One entry per real task; the sub-options (params) are configured in
        // the task detail (sidebar), not flattened into the task list.
        foreach ($this->registeredTasks as $taskId => $task) {
            $module = $task['module'] ?? 'Unknown';
            $label = $task['label'] ?? $taskId;
            $options[$taskId] = sprintf('[%s] %s', $module, $label);
        }

        return $options;
    }

    /**
     * Default value of each param of a task (clean values).
     */
    protected function defaultParams(array $task): array
    {
        $params = [];
        foreach ($task['params'] ?? [] as $key => $definition) {
            if (isset($definition['default'])) {
                $params[$key] = $definition['default'];
            }
        }
        return $params;
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
            if (!in_array($taskId, $enabledTasks, true)) {
                continue;
            }
            $entry = [
                'enabled' => true,
                'frequency' => $data['cron_frequency'] ?? $task['default_frequency'] ?? 'daily',
            ];
            $params = $this->defaultParams($task);
            if ($params) {
                $entry['params'] = $params;
            }
            $settings['tasks'][$taskId] = $entry;
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
