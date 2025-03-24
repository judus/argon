<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\ServiceProviderInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use ReflectionException;

readonly class ServiceProviderRegistry
{
    public function __construct(
        private ServiceContainer $container
    ) {
    }

    /**
     * Registers a service provider and tags it.
     *
     * @param string $className
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function register(string $className): void
    {
        if (!class_exists($className)) {
            throw new ContainerException("Service provider class '$className' does not exist.");
        }

        if (!is_subclass_of($className, ServiceProviderInterface::class)) {
            throw new ContainerException("Service provider '$className' must implement ServiceProviderInterface.");
        }

        $this->container->singleton($className);
        $this->container->tag($className, ['service.provider']);

        /** @var ServiceProviderInterface $provider */
        $provider = $this->container->get($className);
        $provider->register($this->container);
    }

    /**
     * Boots all registered service providers.
     *
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function boot(): void
    {
        $providers = $this->container->getTagged('service.provider');

        foreach ($providers as $provider) {
            $provider->boot($this->container);
        }
    }
}
