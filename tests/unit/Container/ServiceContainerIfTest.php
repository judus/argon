<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ArgonContainer;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Tests\Mocks\MyService;
use Tests\Mocks\TestService;

class ServiceContainerIfTest extends TestCase
{
    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testIfReturnsServiceInstanceForExistingService(): void
    {
        $stub = new TestService('');

        $container = new ArgonContainer();
        $container->singleton(TestService::class, fn() => $stub);

        $container->optional(TestService::class)->callMe();

        $this->assertTrue($stub->called, 'Expected method doSomething to be called.');
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testIfReturnsNoOpForNonExistingService(): void
    {
        $container = new ArgonContainer();

        $result = $container->optional('nonexistent')->callMe(); // returns void, no exception

        $this->assertNull($result);
    }
}
