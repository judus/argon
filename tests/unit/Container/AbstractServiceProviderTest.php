<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\AbstractServiceProvider;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\TestCase;

class AbstractServiceProviderTest extends TestCase
{
    public function testBootDoesNothingByDefault(): void
    {
        $provider = new class extends AbstractServiceProvider {
            public function register(ServiceContainer $container): void
            {
                // No-op for test
            }
        };

        $container = $this->createMock(ServiceContainer::class);

        // No exception = pass
        $provider->boot($container);

        $this->assertTrue(true); // Just assert we got here
    }

    public function testCanBeExtendedWithRegister(): void
    {
        $container = $this->createMock(ServiceContainer::class);
        $container->expects($this->once())->method('singleton')->with('foo');

        $provider = new class extends AbstractServiceProvider {
            public function register(ServiceContainer $container): void
            {
                $container->singleton('foo');
            }
        };

        $provider->register($container);
    }
}
