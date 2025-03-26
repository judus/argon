<?php

declare(strict_types=1);

namespace Tests\Integration\Interceptors;

use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\Interceptors\Post\InitInterceptor;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Interceptors\Mocks\NeedsInit;

class InitInterceptorTest extends TestCase
{
    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testInitMethodIsCalledOnResolvedInstance(): void
    {
        $container = new ServiceContainer();

        $container->singleton(NeedsInit::class);
        $container->registerInterceptor(InitInterceptor::class);

        $instance = $container->get(NeedsInit::class);

        $this->assertTrue($instance->initialized, 'Expected init() to be called automatically.');
    }
}
