<?php

namespace Tests\Integration\Interceptors;

use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ServiceContainer;
use Maduser\Argon\Container\Interceptors\InitMethodInterceptor;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Tests\Integration\Interceptors\Mocks\NeedsInit;

class InitMethodInterceptorTest extends TestCase
{
    /**
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function testInitMethodIsCalledOnResolvedInstance(): void
    {
        $container = new ServiceContainer();

        $container->singleton(NeedsInit::class);
        $container->registerTypeInterceptor(InitMethodInterceptor::class);

        $instance = $container->get(NeedsInit::class);

        $this->assertTrue($instance->initialized, 'Expected init() to be called automatically.');
    }
}