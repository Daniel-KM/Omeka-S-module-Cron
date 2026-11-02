Cron (module for Omeka S)
==========================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__


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

* From the source

If the module was installed from the source, rename the name of the folder of
the module to `Cron`.

- For test

The module includes a comprehensive test suite with unit and functional tests.
Run them from the root of Omeka:

```sh
vendor/bin/phpunit -c modules/Cron/phpunit.xml --testdox
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

When the directory of the module is a symbolic link, for example when modules
are shared between instances, call the script by its path inside the instance
(`/path/to/omeka/modules/Cron/data/scripts/cron.php`), not by the target of the
link: the root of Omeka is found from the path used to call the script.

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

### Backup of the directory "files/"

The task "Backup the "files/" directory" archives the local directory `files/`
as a `tar.gz` file. The destination folder is set in the cron page and is
required: it must be an absolute writable path, outside the directory of Omeka
and outside `files/`. Inside `files/`, the archive would include itself, and
elsewhere in Omeka, it may be downloadable from the web, with the private files.

The other backups (database, modules, themes, config) are managed by the module
[Easy Admin] and are stored in `files/backup/`.


Registering Tasks (For Developers)
----------------------------------

Modules can register their own cron tasks by adding entries to the `cron_tasks`
key in their `module.config.php`:

```php
// In your module's config/module.config.php:
return [
    'cron_tasks' => [
        'my_task_id' => [
            'label' => 'My Task Description', // @translate
            'module' => 'MyModule',
            'description' => 'What the task does.', // @translate Optional
            'frequencies' => ['hourly', 'daily', 'weekly'], // Optional: supported frequencies
            'default_frequency' => 'daily', // Optional
            // Optional: params of the task, each one is a select in the form.
            'params' => [
                'age' => [
                    'label' => 'Age', // @translate
                    'options' => [
                        '1d' => 'older than 1 day', // @translate
                        '1w' => 'older than 1 week', // @translate
                    ],
                    'default' => '1d',
                ],
            ],
        ],
    ],
];
```

The Cron module automatically merges `cron_tasks` from all modules' configurations.

To handle task execution, attach to the `cron.execute` event:

```php
$sharedEventManager->attach(
    \Cron\Job\CronTasks::class,
    'cron.execute',
    function ($event) {
        $taskId = $event->getParam('task_id');
        if ($taskId === 'my_task_id') {
            // The settings of the task: enabled, frequency and params.
            $taskSettings = $event->getParam('task_settings');
            $age = $taskSettings['params']['age'] ?? '1d';
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
            'params' => [
                'age' => '1d',
            ],
        ],
        // ...
    ],
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
[Easy Admin]: https://gitlab.com/Daniel-KM/Omeka-S-module-EasyAdmin
[installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[Cron.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-Cron/-/releases
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Cron/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
