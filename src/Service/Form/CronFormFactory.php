<?php declare(strict_types=1);

namespace Cron\Service\Form;

use Cron\Form\CronForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CronFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new CronForm(null, $options ?? []);

        // Get cron tasks from merged module config.
        $config = $services->get('Config');
        $cronTasksConfig = $config['cron_tasks'] ?? [];

        $form->setCronTasksConfig($cronTasksConfig);

        return $form;
    }
}
