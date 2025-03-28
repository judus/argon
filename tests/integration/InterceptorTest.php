<?php

declare(strict_types=1);

namespace Tests\Integration;

use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;
use Maduser\Argon\Container\Contracts\PreResolutionInterceptorInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Mocks\InterceptedClass;
use Tests\Integration\Mocks\Post1;
use Tests\Integration\Mocks\Post2;
use Tests\Integration\Mocks\PostHook;
use Tests\Integration\Mocks\Pre1;
use Tests\Integration\Mocks\Pre2;
use Tests\Integration\Mocks\PreArgOverride;
use Tests\Integration\Mocks\ShortCircuitInterceptor;
use Tests\Integration\Mocks\SimpleService;

final class InterceptorTest extends TestCase
{
    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testPostInterceptorIsInvoked(): void
    {
        $container = new ServiceContainer();

        $container->registerInterceptor(PostHook::class);

        $instance = $container->get(InterceptedClass::class);

        $this->assertTrue($instance->validated, 'Post interceptor did not validate the object');
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testPreInterceptorModifiesParameters(): void
    {
        $container = new ServiceContainer();

        $container->registerInterceptor(PreArgOverride::class);

        $instance = $container->get(SimpleService::class);

        $this->assertSame('from-interceptor', $instance->value);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testPreInterceptorCanShortCircuit(): void
    {
        $container = new ServiceContainer();

        $container->registerInterceptor(ShortCircuitInterceptor::class);

        $instance = $container->get(SimpleService::class);

        $this->assertSame('intercepted-instance', $instance->value);
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testChainedPreAndPostInterceptorsAreApplied(): void
    {
        $container = new ServiceContainer();

        $container->registerInterceptor(Pre1::class);
        $container->registerInterceptor(Pre2::class);
        $container->registerInterceptor(Post1::class);
        $container->registerInterceptor(Post2::class);

        $instance = $container->get(InterceptedClass::class);

        $this->assertSame('from-pre2', $instance->value, 'Pre2 should override Pre1 value');
        $this->assertTrue($instance->post1, 'Post1 should have marked the instance');
        $this->assertTrue($instance->post2, 'Post2 should have marked the instance');
    }
}
