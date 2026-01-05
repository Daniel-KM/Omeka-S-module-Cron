<?php declare(strict_types=1);

/**
 * Execute cron tasks from system cron.
 *
 * This script is designed to be called from system cron (crontab) to run
 * scheduled tasks configured in the Cron module.
 *
 * Usage:
 *   php modules/Cron/data/scripts/cron.php --user-id=1 --server-url="http://example.com" --base-path="/"
 *
 * Example crontab entry (run daily at midnight):
 *   0 0 * * * php /var/www/html/omeka-s/modules/Cron/data/scripts/cron.php --user-id=1 --server-url="http://example.com"
 *
 * @copyright Daniel Berthereau, 2022-2026
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 *
 * Adapted from EasyAdmin data/scripts/task.php.
 * @todo Use the true Laminas console routing system.
 */

require dirname(__DIR__, 4) . '/bootstrap.php';

use Omeka\Stdlib\Message;

$help = <<<'MSG'
Usage: php modules/Cron/data/scripts/cron.php [arguments]

Required arguments:
  -u --user-id [#id]
        The Omeka user id for permissions (typically 1 for admin).

Recommended arguments:
  -s --server-url [url]
        The url of the server to build resource urls (default:
        "http://localhost").

  -b --base-path [path]
        The path to complete the server url (default: "/").

Optional arguments:
  -a --args [json]
        Additional arguments to pass to the cron job as JSON.

  -k --as-task
        Run as a simple task without creating a job record.
        Useful for frequent lightweight operations.

Other arguments:
  -h --help
        This help.
MSG; // @translate

$userId = null;
$serverUrl = null;
$basePath = null;
$jobArgs = [];
$asTask = false;

$application = \Omeka\Mvc\Application::init(require OMEKA_PATH . '/application/config/application.config.php');
$services = $application->getServiceManager();
/** @var \Laminas\Log\Logger $logger */
$logger = $services->get('Omeka\Logger');
$translator = $services->get('MvcTranslator');

if (php_sapi_name() !== 'cli') {
    $message = new Message(
        'The script "%s" must be run from the command line.', // @translate
        __FILE__
    );
    $logger->err($message);
    exit($translator->translate($message) . PHP_EOL);
}

$shortopts = 'hu:b:s:a:k';
$longopts = ['help', 'user-id:', 'base-path:', 'server-url:', 'args:', 'as-task'];
$options = getopt($shortopts, $longopts);

if (!$options) {
    echo $translator->translate($help) . PHP_EOL;
    exit();
}

$errors = [];

foreach ($options as $key => $value) switch ($key) {
    case 'u':
    case 'user-id':
        $userId = $value;
        break;
    case 's':
    case 'server-url':
        $serverUrl = $value;
        break;
    case 'b':
    case 'base-path':
        $basePath = $value;
        break;
    case 'a':
    case 'args':
        $jobArgs = json_decode($value, true);
        if (!is_array($jobArgs)) {
            $message = new Message(
                'The job arguments are not a valid json object.' // @translate
            );
            $errors[] = $translator->translate($message);
        }
        break;
    case 'k':
    case 'as-task':
        $asTask = true;
        break;
    case 'h':
    case 'help':
        $message = new Message($help);
        echo $translator->translate($message) . PHP_EOL;
        exit();
    default:
        break;
}

if (empty($userId)) {
    $message = new Message(
        'The user id must be set and exist.' // @translate
    );
    $errors[] = $translator->translate($message);
}

/** @var \Doctrine\ORM\EntityManager $entityManager */
$entityManager = $services->get('Omeka\EntityManager');
$hasDatabaseError = false;
$user = null;

if ($userId) {
    try {
        $user = $entityManager->find(\Omeka\Entity\User::class, $userId);
    } catch (\Exception $e) {
        $message = $e->getMessage();
        if (mb_strpos($message, 'could not find driver') !== false) {
            $message = new Message(
                'Database is not available. Check if php-mysql is installed with the php version available on cli.' // @translate
            );
        } else {
            $message = new Message(
                'The database does not exist.' // @translate
            );
        }
        $hasDatabaseError = true;
        $errors[] = $translator->translate($message);
    }

    if (empty($user) && !$hasDatabaseError) {
        $message = new Message(
            'The user #%d does not exist.', // @translate
            $userId
        );
        $logger->err($message);
        $errors[] = $translator->translate($message);
    }
}

if (count($errors)) {
    exit(implode(PHP_EOL, $errors) . PHP_EOL);
}

// Clean vars.
unset($errors, $hasDatabaseError, $help, $longopts, $message, $options, $shortopts);

if (empty($serverUrl)) {
    $serverUrl = 'http://localhost';
    $message = new Message(
        'No server url passed, so use: --server-url "http://localhost"' // @translate
    );
    $logger->notice($message);
    echo $message . PHP_EOL;
}

if (empty($basePath)) {
    $basePath = '/';
    $message = new Message(
        'No base path passed, so use: --base-path "/"' // @translate
    );
    $logger->notice($message);
    echo $message . PHP_EOL;
}

$serverUrlParts = parse_url($serverUrl);
$scheme = $serverUrlParts['scheme'] ?? 'http';
$host = $serverUrlParts['host'] ?? 'localhost';
if (isset($serverUrlParts['port'])) {
    $port = $serverUrlParts['port'];
} elseif ($scheme === 'http') {
    $port = 80;
} elseif ($scheme === 'https') {
    $port = 443;
} else {
    $port = null;
}

/** @var \Laminas\View\Helper\ServerUrl $serverUrlHelper */
$serverUrlHelper = $services->get('ViewHelperManager')->get('ServerUrl');
$serverUrlHelper
    ->setScheme($scheme)
    ->setHost($host)
    ->setPort($port);

$basePath = '/' . trim((string) $basePath, '/');
$services->get('ViewHelperManager')->get('BasePath')->setBasePath($basePath);
$services->get('Router')->setBaseUrl($basePath);

$services->get('Omeka\AuthenticationService')->getStorage()->write($user);

// Get enabled tasks from settings.
$settings = $services->get('Omeka\Settings');
$cronSettings = $settings->get('cron', []);
$enabledTasks = [];
foreach ($cronSettings['tasks'] ?? [] as $taskId => $taskSettings) {
    if (!empty($taskSettings['enabled'])) {
        $enabledTasks[$taskId] = $taskSettings;
    }
}

if (!count($enabledTasks)) {
    $message = new Message('No cron tasks are enabled.'); // @translate
    echo $translator->translate($message) . PHP_EOL;
    $logger->notice($message);
    exit();
}

// Merge enabled tasks with any additional args.
$finalArgs = array_merge($jobArgs, [
    'tasks' => $enabledTasks,
    'manual' => false,
]);

$taskClass = 'Cron\Job\CronTasks';
$referenceId = null;

// Prepare the job.
$job = new \Omeka\Entity\Job;
$job->setOwner($user);
$job->setClass($taskClass);
$job->setArgs($finalArgs);
$job->setPid(getmypid());

if ($asTask) {
    /**
     * @var \Omeka\Module\Manager $moduleManager
     * @var \Omeka\Module\Module $module
     */
    $moduleManager = $services->get('Omeka\ModuleManager');
    $module = $moduleManager->getModule('Log');
    if ($module && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE) {
        $referenceId = 'cron:' . (new \DateTime())->format('Ymd-His');
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId($referenceId);
        $logger->addProcessor($referenceIdProcessor);
        $userIdProcessor = new \Log\Log\Processor\UserId($user);
        $logger->addProcessor($userIdProcessor);
    }

    // Since there is no job id (not persisted), shouldStop() would fail.
    // This dynamic subclass overrides shouldStop() to return false.
    require_once OMEKA_PATH . '/modules/Cron/src/Job/CronTasks.php';

    $task = new class($job, $services) extends \Cron\Job\CronTasks {
        public function shouldStop()
        {
            return $this->job->getId()
                ? parent::shouldStop()
                : false;
        }
    };
} else {
    $entityManager->persist($job);
    $entityManager->flush();
}

$jobId = $job->getId();

if ($referenceId && $jobId) {
    $message = new Message('Cron is starting with %1$d tasks (job: #%2$d, reference: %3$s).', count($enabledTasks), $jobId, $referenceId); // @translate
} elseif ($referenceId) {
    $message = new Message('Cron is starting with %1$d tasks (reference: %2$s).', count($enabledTasks), $referenceId); // @translate
} elseif ($jobId) {
    $message = new Message('Cron is starting with %1$d tasks (job: #%2$d).', count($enabledTasks), $jobId); // @translate
} else {
    $message = new Message('Cron is starting with %d tasks.', count($enabledTasks)); // @translate
}

echo $translator->translate($message) . PHP_EOL;
$logger->info($message);

// Update last run time.
$settings->set('cron_last', time());

try {
    if ($asTask) {
        $task->perform();
    } else {
        $strategy = $services->get('Omeka\Job\DispatchStrategy\Synchronous');
        $services->get('Omeka\Job\Dispatcher')->send($job, $strategy);
        $job->setPid(null);
        $entityManager->flush();
    }
} catch (\Exception $e) {
    $message = new Message('Cron has an error: %s', $e->getMessage()); // @translate
    echo $translator->translate($message) . PHP_EOL;
    $logger->err($e);
    exit(1);
}

$message = new Message('Cron completed.'); // @translate
$logger->info($message);
echo $translator->translate($message) . PHP_EOL;
