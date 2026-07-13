<?php declare(strict_types=1);

namespace Cron\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Stdlib\Message;

class CronController extends AbstractActionController
{
    public function indexAction()
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        /** @var \Cron\Form\CronForm $form */
        $form = $services->get('FormElementManager')->get(\Cron\Form\CronForm::class);
        $form->init();

        // Load current settings.
        $cronSettings = $settings->get('cron', []);

        // Convert settings to form data.
        $formData = $form->prepareDataFromSettings($cronSettings);
        $formData['cron_backup_dir'] = $settings->get('cron_backup_dir', '');
        $formData['cron_backup_files_dir'] = $settings->get('cron_backup_files_dir', '');
        $form->setData($formData);

        // Prepare view data.
        $lastRun = $settings->get('cron_last');
        $cronCommand = $this->buildCronCommand();

        // Check if EasyAdmin is present for navigation context.
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $easyAdminModule = $moduleManager->getModule('EasyAdmin');
        $hasEasyAdmin = $easyAdminModule && $easyAdminModule->getState() === \Omeka\Module\Manager::STATE_ACTIVE;

        $view = new ViewModel([
            'form' => $form,
            'lastRun' => $lastRun,
            'cronCommand' => $cronCommand,
            'registeredTasks' => $form->getRegisteredTasks(),
            'hasEasyAdmin' => $hasEasyAdmin,
        ]);

        $request = $this->getRequest();
        if (!$request->isPost()) {
            return $view;
        }

        $params = $request->getPost();

        // Handle "Run now" action.
        if (!empty($params['run_now'])) {
            return $this->runNow();
        }

        $form->setData($params);
        if (!$form->isValid()) {
            $this->messenger()->addErrors($form->getMessages());
            return $view;
        }

        $data = $form->getData();
        unset($data['csrf']);

        // Default backup folder: must be an absolute, writable path if set.
        $backupDir = trim((string) ($data['cron_backup_dir'] ?? ''));
        if ($backupDir !== '' && !$this->prepareBackupDir($backupDir)) {
            $this->messenger()->addError(new Message(
                'The backup folder "%s" must be an absolute, writable path.', // @translate
                $backupDir
            ));
            return $view;
        }
        $settings->set('cron_backup_dir', $backupDir);

        // Folder for the "files/" backup: absolute, writable and outside
        // "files/" (a sub-folder would make the backup archive itself).
        $backupFilesDir = trim((string) ($data['cron_backup_files_dir'] ?? ''));
        if ($backupFilesDir !== '') {
            if (!$this->prepareBackupDir($backupFilesDir)) {
                $this->messenger()->addError(new Message(
                    'The files backup folder "%s" must be an absolute, writable path.', // @translate
                    $backupFilesDir
                ));
                return $view;
            }
            if ($this->isInsideFilesDir($backupFilesDir)) {
                $this->messenger()->addError(new Message(
                    'The files backup folder "%s" must be outside "files/" (not a sub-folder).', // @translate
                    $backupFilesDir
                ));
                return $view;
            }
        }
        $settings->set('cron_backup_files_dir', $backupFilesDir);

        // Convert form data to settings structure.
        $newSettings = $form->prepareSettingsFromData($data);
        $settings->set('cron', $newSettings);

        $enabledCount = 0;
        foreach ($newSettings['tasks'] ?? [] as $taskSettings) {
            if (!empty($taskSettings['enabled'])) {
                $enabledCount++;
            }
        }

        if ($enabledCount) {
            $msg = new Message(
                '%d tasks defined to be run regularly.', // @translate
                $enabledCount
            );
        } else {
            $msg = new Message(
                'No task defined to be run regularly.' // @translate
            );
        }
        $this->messenger()->addSuccess($msg);

        return $this->redirect()->toRoute(null, [], true);
    }

    /**
     * Check a backup folder: it must be an absolute path and writable (it is
     * created when missing).
     */
    protected function prepareBackupDir(string $dir): bool
    {
        // Require an absolute path (unix "/…" or windows "C:\…").
        if ($dir[0] !== '/' && !preg_match('~^[A-Za-z]:[\\\\/]~', $dir)) {
            return false;
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return is_dir($dir) && is_writable($dir);
    }

    /**
     * Whether a directory is the local "files/" directory or a sub-folder of it
     * (backing "files/" up into itself would recurse).
     */
    protected function isInsideFilesDir(string $dir): bool
    {
        $config = $this->getServiceLocator()->get('Config');
        $filesPath = $config['file_store']['local']['base_path'] ?? null;
        $filesPath = $filesPath ?: (OMEKA_PATH . '/files');
        $filesReal = rtrim(realpath($filesPath) ?: $filesPath, '/') . '/';
        $dirReal = rtrim(realpath($dir) ?: $dir, '/') . '/';
        return strpos($dirReal, $filesReal) === 0;
    }

    /**
     * Run all enabled tasks immediately.
     */
    protected function runNow()
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        $cronSettings = $settings->get('cron', []);
        $enabledTasks = [];
        foreach ($cronSettings['tasks'] ?? [] as $taskId => $taskSettings) {
            if (!empty($taskSettings['enabled'])) {
                $enabledTasks[$taskId] = $taskSettings;
            }
        }

        if (!count($enabledTasks)) {
            $this->messenger()->addWarning(new Message(
                'No tasks are enabled.' // @translate
            ));
            return $this->redirect()->toRoute(null, [], true);
        }

        // Dispatch the cron job to run all tasks.
        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
        $job = $dispatcher->dispatch(\Cron\Job\CronTasks::class, [
            'tasks' => $enabledTasks,
            'manual' => true,
        ]);

        // Update last run time.
        $settings->set('cron_last', time());

        $urlPlugin = $this->url();
        $message = new Message(
            'Processing cron tasks in background (job %s#%d%s, %slogs%s).', // @translate
            sprintf(
                '<a href="%s">',
                htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
            ),
            $job->getId(),
            '</a>',
            class_exists('Log\Module', false)
                ? sprintf('<a href="%1$s">', $urlPlugin->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]]))
                : sprintf('<a href="%1$s" target="_blank">', $urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()])),
            '</a>'
        );
        $message->setEscapeHtml(false);
        $this->messenger()->addSuccess($message);

        return $this->redirect()->toRoute(null, [], true);
    }

    /**
     * Build the cron command suggestion.
     */
    protected function buildCronCommand(): string
    {
        $services = $this->getServiceLocator();

        /** @var \Laminas\View\Helper\ServerUrl $serverUrl */
        $serverUrl = $services->get('ViewHelperManager')->get('ServerUrl');
        $basePath = $services->get('ViewHelperManager')->get('BasePath');

        // Check for Cron module script (preferred) or EasyAdmin fallback.
        $cronScript = dirname(__DIR__, 3) . '/data/scripts/cron.php';
        $easyAdminScript = (is_dir(OMEKA_PATH . '/modules/EasyAdmin') ? OMEKA_PATH . '/modules/EasyAdmin' : OMEKA_PATH . '/composer-addons/modules/EasyAdmin') . '/data/scripts/task.php';

        if (file_exists($cronScript)) {
            // Cron module's native script - no --task needed, runs all enabled tasks.
            return sprintf(
                '0 0 * * * php %s --user-id=1 --server-url="%s" --base-path="%s"',
                $cronScript,
                rtrim($serverUrl(), '/'),
                $basePath()
            );
        } elseif (file_exists($easyAdminScript)) {
            // Fallback to EasyAdmin's task.php - requires --task argument.
            return sprintf(
                '0 0 * * * php %s --task="Cron\\Job\\CronTasks" --user-id=1 --server-url="%s" --base-path="%s"',
                $easyAdminScript,
                rtrim($serverUrl(), '/'),
                $basePath()
            );
        } else {
            // No script available - suggest the native cron script path.
            return sprintf(
                '0 0 * * * php %s --user-id=1 --server-url="%s" --base-path="%s"',
                $cronScript,
                rtrim($serverUrl(), '/'),
                $basePath()
            );
        }
    }

    /**
     * Helper to get service locator (for controllers).
     */
    protected function getServiceLocator()
    {
        return $this->getEvent()->getApplication()->getServiceManager();
    }
}
