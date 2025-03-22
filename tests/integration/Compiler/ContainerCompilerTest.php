<?php

namespace Tests\Integration\Compiler;

use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ServiceContainer;
use Maduser\Argon\Container\ContainerCompiler;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Tests\Integration\Compiler\Mocks\Logger;
use Tests\Integration\Compiler\Mocks\Mailer;

class ContainerCompilerTest extends TestCase
{
    private string $cacheFile = __DIR__ . '/CachedContainer.php';

    public function setUp(): void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function testCompiledContainerResolvesSingletons(): void
    {
        $container = new ServiceContainer();

        $container->singleton(Logger::class, fn() => new Logger());
        $container->singleton(Mailer::class, fn() => new Mailer($container->get(Logger::class)));

        $compiler = new ContainerCompiler($container);
        $compiler->compileToFile($this->cacheFile);

        require_once $this->cacheFile;

        $compiled = new \CachedContainer();

        $mailer = $compiled->get(Mailer::class);

        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertInstanceOf(Logger::class, $mailer->logger);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function testCompiledContainerResolvesTransients(): void
    {
        $container = new ServiceContainer();

        $container->bind(Logger::class, Logger::class);
        $container->bind(Mailer::class, Mailer::class);

        $compiler = new ContainerCompiler($container);
        $compiler->compileToFile($this->cacheFile);

        require_once $this->cacheFile;

        $compiled = new \CachedContainer();

        $mailer = $compiled->get(Mailer::class);

        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertInstanceOf(Logger::class, $mailer->logger);
    }
}
