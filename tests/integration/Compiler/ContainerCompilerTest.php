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
        $this->cacheFile = __DIR__ . '/CachedContainerA.php';
        $compiler->compileToFile($this->cacheFile, 'CachedContainerA');

        require_once $this->cacheFile;

        $compiled = new \CachedContainerA();

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
        $this->cacheFile = __DIR__ . '/CachedContainerB.php';
        $compiler->compileToFile($this->cacheFile, 'CachedContainerB');

        require_once $this->cacheFile;

        $compiled = new \CachedContainerB();

        $mailer = $compiled->get(Mailer::class);

        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertInstanceOf(Logger::class, $mailer->logger);
    }

    /**
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function testCompiledContainerPreservesTags(): void
    {
        $container = new ServiceContainer();

        $container->singleton(Logger::class, Logger::class);
        $container->singleton(Mailer::class, Mailer::class);

        // Tag the services
        $container->tag(Logger::class, ['loggers']);
        $container->tag(Mailer::class, ['mailers', 'loggers']);

        // Compile
        $compiler = new ContainerCompiler($container);
        $this->cacheFile = __DIR__ . '/CachedContainerC.php';
        $compiler->compileToFile($this->cacheFile, 'CachedContainerC');

        require_once $this->cacheFile;
        $compiled = new \CachedContainerC();

        // Check tagged services
        $loggers = $compiled->getTaggedServices('loggers');
        $mailers = $compiled->getTaggedServices('mailers');

        $this->assertCount(2, $loggers);
        $this->assertCount(1, $mailers);

        $this->assertInstanceOf(Logger::class, $loggers[0]);
        $this->assertInstanceOf(Mailer::class, $loggers[1]);
        $this->assertInstanceOf(Mailer::class, $mailers[0]);
    }
}
