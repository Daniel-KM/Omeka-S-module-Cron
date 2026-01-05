Cron (module for Omeka S)
==========================

> __New versions of this module and calculation samples are available on
> [GitLab], which is the main repository. Issues should be opened there.__

[Cron] is a module for [Omeka S] that provides a scheduled task system allowing
modules to register and execute recurring tasks.


Features
--------

- Configurable task scheduling (hourly, daily, weekly, monthly)
- Event-based task registration for other modules
- Manual "Run now" functionality
- Fallback execution on page load when server cron unavailable
- Integration with EasyAdmin navigation when installed
- Standalone admin menu when EasyAdmin is not present


Installation
------------

See general end user documentation for [installing a module].

* From the zip

Download the last release [Cron.zip] from the list of releases, and uncompress
it in the `modules` directory.

* From the source and calculation samples

If the module was installed from the source, rename the name of the folder of
the module to `Cron`.

- For test

The module includes a comprehensive test suite with unit and functional tests.
Run them from the root of Omeka:

```sh
vendor/bin/phpunit -c modules/Cron/test/phpunit.xml --testdox
```


Configuration
-------------

### Server Cron (Recommended)

For reliable task execution, configure a server cron job using the module's
native script:

```bash
0 0 * * * php /path/to/omeka/modules/Cron/data/scripts/cron.php --user-id=1 --server-url="https://example.org" --base-path="/"
```

The script automatically runs all enabled tasks configured in the admin
interface.

#### Script Options

| Option | Description |
|--------|-------------|
| `-u`, `--user-id` | Required. Omeka user ID for permissions (typically 1 for admin). |
| `-s`, `--server-url` | The server URL for building resource URLs (default: `http://localhost`). |
| `-b`, `--base-path` | The base path to complete the server URL (default: `/`). |
| `-a`, `--args` | Additional arguments to pass to the cron job as JSON. |
| `-k`, `--as-task` | Run as a simple task without creating a job record. |
| `-h`, `--help` | Display help message. |

#### Schedule Examples

Adjust the cron schedule as needed:
- `0 * * * *` - Hourly (at minute 0)
- `0 0 * * *` - Daily (at midnight)
- `0 0 * * 0` - Weekly (Sunday at midnight)
- `0 0 1 * *` - Monthly (first day at midnight)

### EasyAdmin Fallback

If EasyAdmin is installed and the Cron script is not available, you can use
EasyAdmin's task script instead:

```bash
0 0 * * * php /path/to/omeka/modules/EasyAdmin/data/scripts/task.php --task="Cron\Job\CronTasks" --user-id=1 --server-url="https://example.org" --base-path="/"
```

### Fallback Mode (No Server Cron)

Without server cron, tasks execute on page load based on configured frequency.
This is less reliable as it depends on site traffic.


Registering Tasks (For Developers)
----------------------------------

Modules can register their own cron tasks by attaching to the `cron.tasks` event:

```php
// In your module's attachListeners() method:
$sharedEventManager->attach(
    \Cron\Form\CronForm::class,
    'cron.tasks',
    function ($event) {
        $tasks = $event->getParam('tasks', []);
        $tasks['my_task_id'] = [
            'label' => 'My Task Description', // @translate
            'module' => 'MyModule',
            'job' => \MyModule\Job\MyJob::class, // Optional: job class to dispatch
            'frequencies' => ['hourly', 'daily', 'weekly'], // Optional: supported frequencies
            'default_frequency' => 'daily', // Optional
            'options' => [ // Optional: sub-options for configurable tasks
                'option_1' => 'Option 1 label',
                'option_2' => 'Option 2 label',
            ],
        ];
        $event->setParam('tasks', $tasks);
    }
);
```

To handle task execution, attach to the `cron.execute` event:

```php
$sharedEventManager->attach(
    \Cron\Job\CronTasks::class,
    'cron.execute',
    function ($event) {
        $taskId = $event->getParam('task_id');
        if ($taskId === 'my_task_id') {
            // Execute your task logic here
            $logger = $event->getParam('logger');
            $logger->info('Executing my task...');

            // Mark as handled
            $event->setParam('handled', true);
        }
    }
);
```


Settings Structure
------------------

Settings are stored with the key `cron`:

```php
[
    'tasks' => [
        'task_id' => [
            'enabled' => true,
            'frequency' => 'daily',
        ],
        // ...
    ],
    'global_frequency' => 'daily',
]
```


Warning
-------

Use it at your own risk.

It's always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software's author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user's attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software's suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.


Copyright
---------

* Copyright Daniel Berthereau, 2022-2026 (see [Daniel-KM] on GitLab)


[Cron]: https://gitlab.com/Daniel-KM/Omeka-S-module-Cron
[Omeka S]: https://omeka.org/s
[installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[Cron.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-Cron/-/releases
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Cron/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
