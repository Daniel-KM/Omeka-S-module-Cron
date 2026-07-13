<?php declare(strict_types=1);

namespace Cron;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'form_elements' => [
        'invokables' => [
        ],
        'factories' => [
            Form\CronForm::class => Service\Form\CronFormFactory::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            Controller\Admin\CronController::class => Controller\Admin\CronController::class,
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
        ],
        'factories' => [
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    // Standalone route when EasyAdmin is not present.
                    // When EasyAdmin is present, it provides admin/easy-admin/cron.
                    'cron' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/cron',
                            'defaults' => [
                                '__NAMESPACE__' => 'Cron\Controller\Admin',
                                'controller' => Controller\Admin\CronController::class,
                                'action' => 'index',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => class_exists('EasyAdmin\\Module', false) ? [] : [
            // Navigation when EasyAdmin is not present.
            // When EasyAdmin is present, it provides its own navigation.
            'cron' => [
                'label' => 'Cron', // @translate
                'route' => 'admin/cron',
                'controller' => 'cron',
                'action' => 'index',
                'resource' => Controller\Admin\CronController::class,
                'privilege' => 'index',
                'class' => 'o-icon- fa-clock',
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'cron' => [
        'settings' => [
            'cron' => [
                'tasks' => [],
                'global_frequency' => 'daily',
            ],
        ],
    ],
    // Tasks registered by modules. Each module adds its tasks here.
    // Structure:
    // 'cron_tasks' => [
    //     'task_id' => [
    //         'label' => 'Task description', // @translate
    //         'module' => 'ModuleName',
    //         'job' => \Module\Job\TaskJob::class, // Optional
    //         'frequencies' => ['hourly', 'daily'], // Optional
    //         'default_frequency' => 'daily', // Optional
    //         'options' => [ // Optional: sub-options for configurable tasks
    //             'option_id' => 'Option label',
    //         ],
    //     ],
    // ],
    'cron_tasks' => [
        // Distinct from EasyAdmin's "backup_files" (modules/themes/config):
        // this one archives the whole local "files/" directory.
        'backup_files_dir' => [
            'label' => 'Backup the "files/" directory', // @translate
            'module' => 'Cron',
            'frequencies' => ['daily', 'weekly', 'monthly'],
            'default_frequency' => 'weekly',
        ],
    ],
];
