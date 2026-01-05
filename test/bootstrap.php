<?php declare(strict_types=1);

/**
 * Bootstrap file for Cron module tests.
 *
 * Use Common module Bootstrap helper for test setup.
 */

require dirname(__DIR__, 3) . '/bootstrap.php';
require dirname(__DIR__, 3) . '/modules/Common/test/Bootstrap.php';

\CommonTest\Bootstrap::bootstrap(
    [
        'Common',
        'Cron',
    ],
    'CronTest',
    __DIR__ . '/CronTest'
);
