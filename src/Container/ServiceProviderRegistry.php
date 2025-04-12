<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\ServiceProviderInterface;
use Maduser\Argon\Container\Contracts\ServiceProviderRegistryInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;

/**
 * Registers and boots service providers via the container.
 */
final readonly class ServiceProviderRegistry implements ServiceProviderRegistryInterface
{
    public function __construct(
        private ArgonContainer $container
    ) {
    }

    /**
     * Registers a service provider and tags it.
     *
     * @param class-string<ServiceProviderInterface> $className
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function register(string $className): void
    {
        if (!class_exists($className)) {
            throw new ContainerException("Service provider class '$className' does not exist.");
        }

        if (!is_subclass_of($className, ServiceProviderInterface::class)) {
            throw new ContainerException("Service provider '$className' must implement ServiceProviderInterface.");
        }

        $this->container->set($className);
        $this->container->tag($className, ['service.provider']);

        $provider = $this->container->get($className);
        assert($provider instanceof ServiceProviderInterface);

        $provider->register($this->container);
    }

    /**
     * Boots all registered service providers.
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function boot(): void
    {
        $providers = $this->container->getTagged('service.provider');

        foreach ($providers as $provider) {
            assert($provider instanceof ServiceProviderInterface);
            $provider->boot($this->container);
        }
    }
}
