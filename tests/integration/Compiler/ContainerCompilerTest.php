<?php

namespace Tests\Integration\Compiler;

use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ServiceContainer;
use Maduser\Argon\Container\ContainerCompiler;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use stdClass;
use Tests\Integration\Compiler\Mocks\Logger;
use Tests\Integration\Compiler\Mocks\LoggerInterceptor;
use Tests\Integration\Compiler\Mocks\Mailer;
use Tests\Integration\Compiler\Mocks\TestServiceWithMultipleParams;
use Tests\Mocks\DummyProvider;

class ContainerCompilerTest extends TestCase
{
    private function compileAndLoadContainer(
        ServiceContainer $container,
        string $className,
    ): object {
        $namespace = 'Tests\\Integration\\Compiler';

        $file = __DIR__ . "/../../resources/cache/$className.php";
        if (file_exists($file)) {
            unlink($file);
        }

        $compiler = new ContainerCompiler($container);
        $compiler->compileToFile($file, $className, $namespace);
        require_once $file;

        $fqcn = "$namespace\\$className";
        return new $fqcn();
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function testCompiledContainerResolvesClosures(): void
    {
        $container = new ServiceContainer();

        $container->singleton(Logger::class, fn() => new Logger());
        $container->singleton(Mailer::class, fn() => new Mailer($container->get(Logger::class)));

        $compiled = $this->compileAndLoadContainer($container, 'CachedContainerA');

        $mailer = $compiled->get(Mailer::class);

        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertInstanceOf(Logger::class, $mailer->logger);
    }

    /**
     * @throws ContainerException
     */
    public function testCompiledContainerResolvesSingletons(): void
    {
        $container = new ServiceContainer();

        $container->singleton(Logger::class);
        $container->singleton(Mailer::class);

        $compiled = $this->compileAndLoadContainer($container, 'CachedContainerB');

        $mailer = $compiled->get(Mailer::class);
        $mailer2 = $compiled->get(Mailer::class);
        $logger = $compiled->get(Logger::class);
        $logger2 = $compiled->get(Logger::class);

        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertInstanceOf(Logger::class, $mailer->logger);

        // Singleton assertions
        $this->assertSame($mailer, $mailer2, 'Mailer should be a singleton');
        $this->assertSame($logger, $logger2, 'Logger should be a singleton');
        $this->assertSame($logger, $mailer->logger, 'Mailer.logger should be the same Logger singleton');
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function testCompiledContainerResolvesTransients(): void
    {
        $container = new ServiceContainer();

        $container->bind(Logger::class);
        $container->bind(Mailer::class);

        $compiled = $this->compileAndLoadContainer($container, 'CachedContainerC');

        $mailer = $compiled->get(Mailer::class);

        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertInstanceOf(Logger::class, $mailer->logger);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function testCompiledContainerResolvesWithPrimitiveOverrides(): void
    {
        $container = new ServiceContainer();

        // Set primitive parameter overrides before compiling
        $container->getParameters()->set(TestServiceWithMultipleParams::class, [
            'param1' => 'compiled-override',
            'param2' => 99,
        ]);

        // Bind the service
        $container->bind(TestServiceWithMultipleParams::class);

        // Compile and load container
        $compiled = $this->compileAndLoadContainer($container, 'CachedContainerD');

        // Resolve and assert
        /** @var TestServiceWithMultipleParams $service */
        $service = $compiled->get(TestServiceWithMultipleParams::class);

        $this->assertEquals('compiled-override', $service->getParam1());
        $this->assertEquals(99, $service->getParam2());
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function testCompiledContainerPreservesTags(): void
    {
        $container = new ServiceContainer();

        $container->singleton(Logger::class);
        $container->singleton(Mailer::class);

        // Tag the services
        $container->tag(Logger::class, ['loggers']);
        $container->tag(Mailer::class, ['mailers', 'loggers']);

        // Compile
        $compiled = $this->compileAndLoadContainer($container, 'CachedContainerE');

        // Check tagged services
        $loggers = $compiled->getTagged('loggers');
        $mailers = $compiled->getTagged('mailers');

        $this->assertCount(2, $loggers);
        $this->assertCount(1, $mailers);

        $this->assertInstanceOf(Logger::class, $loggers[0]);
        $this->assertInstanceOf(Mailer::class, $loggers[1]);
        $this->assertInstanceOf(Mailer::class, $mailers[0]);
    }

    /**
     * @throws ContainerException
     */
    public function testCompiledContainerAppliesInterceptor(): void
    {
        $container = new ServiceContainer();
        $container->singleton(Logger::class);
        $container->registerTypeInterceptor(LoggerInterceptor::class);

        $compiled = $this->compileAndLoadContainer($container, 'CachedContainerF');

        /** @var Logger $logger */
        $logger = $compiled->get(Logger::class);

        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertTrue($logger->intercepted, 'Logger instance should be intercepted.');
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function testCompiledContainerIncludesServiceProviders(): void
    {
        $container = new ServiceContainer();
        $container->registerServiceProvider(DummyProvider::class);

        $compiled = $this->compileAndLoadContainer($container, 'CachedContainerG');

        // Ensure service provider is still tagged
        $providers = $compiled->getTagged('service.provider');
        $this->assertNotEmpty($providers, 'Expected at least one tagged service provider');

        $this->assertInstanceOf(DummyProvider::class, $providers[0]);

        // Ensure the service registered by the provider is present
        $this->assertTrue($compiled->has('dummy.service'));
        $this->assertInstanceOf(stdClass::class, $compiled->get('dummy.service'));
    }
}
