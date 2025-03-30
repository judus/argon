<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\AbstractServiceProvider;
use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\ServiceBinder;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Container\Mocks\SomeClass;

class AbstractServiceProviderTest extends TestCase
{
    public function testBootDoesNothingByDefault(): void
    {
        $provider = new class extends AbstractServiceProvider {
            public function register(ArgonContainer $container): void
            {
                // No-op for test
            }
        };

        $container = $this->createMock(ArgonContainer::class);

        // No exception = pass
        $provider->boot($container);

        $this->assertTrue(true); // Just assert we got here
    }

    /**
     * @throws ContainerException
     */
    public function testCanBeExtendedWithRegister(): void
    {
        $binder = new ServiceBinder();

        $container = $this->getMockBuilder(ArgonContainer::class)
            ->onlyMethods(['singleton'])
            ->setConstructorArgs([ 'binder' => $binder ])
            ->getMock();

        $bindingBuilder = $binder->singleton(SomeClass::class);

        $container->expects($this->once())
            ->method('singleton')
            ->with(SomeClass::class)
            ->willReturn($bindingBuilder);

        $provider = new class extends AbstractServiceProvider {
            public function register(ArgonContainer $container): void
            {
                $container->singleton(SomeClass::class);
            }
        };

        $provider->register($container);
    }
}
