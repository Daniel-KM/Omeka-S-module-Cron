<?php declare(strict_types=1);

namespace Cron\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

/**
 * Cron configuration form.
 *
 * Tasks are registered via the 'cron_tasks' key in module.config.php. Each
 * task: label, module, job (optional), frequencies (optional),
 * default_frequency (optional), params (optional: key => [label, options,
 * default]).
 *
 * The per-task configuration (enabled, frequency, params) is rendered by the
 * view as plain inputs "cron[tasks][taskId][…]" and read from the post by the
 * controller (master-detail sidebar ui). This form only holds the csrf token
 * and the backup folders.
 */
class CronForm extends Form
{
    /**
     * @var array Cron tasks from merged module config.
     */
    protected $cronTasksConfig = [];

    /**
     * Set cron tasks from config (called by the factory with merged config).
     */
    public function setCronTasksConfig(array $cronTasksConfig): self
    {
        $this->cronTasksConfig = $cronTasksConfig;
        return $this;
    }

    public function init(): void
    {
        $this->setAttribute('id', 'form-cron');

        $this
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
            'name' => 'cron_backup_dir',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'cron_backup_files_dir',
            'required' => false,
        ]);
    }

    /**
     * Get all registered tasks (from merged module config).
     */
    public function getRegisteredTasks(): array
    {
        return $this->cronTasksConfig;
    }
}
