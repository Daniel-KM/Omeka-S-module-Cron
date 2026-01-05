<?php declare(strict_types=1);

namespace CronTest;

use Omeka\Entity\User;

/**
 * Trait with common helper methods for Cron tests.
 */
trait CronTestTrait
{
    /**
     * @var array IDs of created resources for cleanup.
     */
    protected $createdJobs = [];

    /**
     * Login as admin user.
     */
    protected function loginAdmin(): void
    {
        $serviceLocator = $this->getApplication()->getServiceManager();
        $auth = $serviceLocator->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    /**
     * Logout current user.
     */
    protected function logout(): void
    {
        $serviceLocator = $this->getApplication()->getServiceManager();
        $auth = $serviceLocator->get('Omeka\AuthenticationService');
        $auth->clearIdentity();
    }

    /**
     * Get current authenticated user.
     */
    protected function getCurrentUser(): ?User
    {
        $serviceLocator = $this->getApplication()->getServiceManager();
        $auth = $serviceLocator->get('Omeka\AuthenticationService');
        return $auth->getIdentity();
    }

    /**
     * Get the API manager.
     */
    protected function api(): \Omeka\Api\Manager
    {
        return $this->getApplication()->getServiceManager()->get('Omeka\ApiManager');
    }

    /**
     * Get the entity manager.
     */
    protected function getEntityManager(): \Doctrine\ORM\EntityManager
    {
        return $this->getApplication()->getServiceManager()->get('Omeka\EntityManager');
    }

    /**
     * Get the settings service.
     */
    protected function settings(): \Omeka\Settings\Settings
    {
        return $this->getApplication()->getServiceManager()->get('Omeka\Settings');
    }

    /**
     * Get a service from the service locator.
     */
    protected function getService(string $name)
    {
        return $this->getApplication()->getServiceManager()->get($name);
    }

    /**
     * Clean up created resources.
     */
    protected function cleanupResources(): void
    {
        // Jobs are cleaned up automatically by the job system.
        $this->createdJobs = [];
    }

    /**
     * Dispatch a job and return the job entity.
     */
    protected function dispatchJob(string $class, array $args = []): \Omeka\Entity\Job
    {
        $dispatcher = $this->getService('Omeka\Job\Dispatcher');
        $job = $dispatcher->dispatch($class, $args);
        $this->createdJobs[] = $job->getId();
        return $job;
    }

    /**
     * Get the CronForm.
     */
    protected function getCronForm(): \Cron\Form\CronForm
    {
        $formElementManager = $this->getService('FormElementManager');
        return $formElementManager->get(\Cron\Form\CronForm::class);
    }
}
