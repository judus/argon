<?php

declare(strict_types=1);

namespace Tests\Integration\Container;

use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\TestCase;
use stdClass;

class ServiceContainerTest extends TestCase
{
    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testPostInterceptorModifiesResolvedInstance(): void
    {
        // Define a concrete interceptor class inline for clarity/testing
        $interceptor = new class implements PostResolutionInterceptorInterface {
            public static function supports(object|string $target): bool
            {
                return $target === stdClass::class || $target instanceof stdClass;
            }

            public function intercept(object $instance): void
            {
                $instance->intercepted = true;
            }
        };

        // Register interceptor as FQCN (as expected now)
        $container = new ServiceContainer();
        $container->registerInterceptor(get_class($interceptor));

        // Bind a service (autowiring would also work)
        $container->bind('service', fn() => new \stdClass());

        // Resolve the service
        $instance = $container->get('service');

        // Assertion
        $this->assertTrue($instance->intercepted ?? false, 'Service instance should be intercepted.');
    }
}
