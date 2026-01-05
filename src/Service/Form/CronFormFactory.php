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

        // Set up EventManager with proper identifiers for SharedEventManager.
        $eventManager = $services->get('EventManager');
        $eventManager->setIdentifiers([
            CronForm::class,
            'Cron\Form\CronForm',
        ]);
        $form->setEventManager($eventManager);

        return $form;
    }
}
