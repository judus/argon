<?php

declare(strict_types=1);

namespace Tests\Integration\Interceptors;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Compiler\Mocks\DependentLoggerInterceptor;
use Tests\Integration\Compiler\Mocks\Logger;
use Tests\Integration\Mocks\CustomLogger;

final class ContainerResolvedInterceptorTest extends TestCase
{
    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testInterceptorsReceiveDependenciesFromContainer(): void
    {
        $container = new ArgonContainer();
        $container->set(Logger::class);
        $container->set(CustomLogger::class);

        $container->registerInterceptor(DependentLoggerInterceptor::class);

        $logger = $container->get(Logger::class);

        $this->assertSame('[custom] interceptor', $logger->note);
        $this->assertTrue($logger->intercepted);
    }
}
