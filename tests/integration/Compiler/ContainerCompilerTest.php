<?php

declare(strict_types=1);

namespace Tests\Integration\Compiler;

use Maduser\Argon\Container\ArgumentMap;
use Maduser\Argon\Container\Compiler\ContainerCompiler;
use Maduser\Argon\Container\Contracts\ReflectionCacheInterface;
use Maduser\Argon\Container\Contracts\ServiceDescriptorInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use stdClass;
use Tests\Integration\Compiler\Mocks\ClassWithParamWithoutDefault;
use Tests\Integration\Compiler\Mocks\DefaultValueService;
use Tests\Integration\Compiler\Mocks\Logger;
use Tests\Integration\Compiler\Mocks\LoggerInterceptor;
use Tests\Integration\Compiler\Mocks\Mailer;
use Tests\Integration\Compiler\Mocks\TestServiceWithMultipleParams;
use Tests\Integration\Mocks\CustomLogger;
use Tests\Integration\Mocks\LoggerInterface;
use Tests\Integration\Mocks\NeedsLogger;
use Tests\Integration\Mocks\PreArgOverride;
use Tests\Integration\Mocks\SimpleService;
use Tests\Mocks\DummyProvider;
use Tests\Unit\Container\Mocks\NonInstantiableClass;

class ContainerCompilerTest extends TestCase
{
    /**
     * @throws ContainerException
     * @throws ReflectionException
     */
    private function compileAndLoadContainer(ServiceContainer $container, string $className): object
    {
        $namespace = 'Tests\\Integration\\Compiler';
        $file = __DIR__ . "/../../resources/cache/{$className}.php";

        if (file_exists($file)) {
            unlink($file);
        }

        $compiler = new ContainerCompiler($container);
        $compiler->compile($file, $className, $namespace);

        require_once $file;

        $fqcn = "{$namespace}\\{$className}";
        if (!class_exists($fqcn)) {
            throw new \RuntimeException("Failed to load compiled container class: $fqcn");
        }

        return new $fqcn();
    }



    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testCompiledContainerDoesNotResolveClosures(): void
    {
        $container = new ServiceContainer();

        $this->expectException(ContainerException::class);

        $container->singleton(Logger::class, fn() => new Logger());
        $container->singleton(Mailer::class, fn() => new Mailer($container->get(Logger::class)));

        $compiled = $this->compileAndLoadContainer($container, 'testCompiledContainerResolvesClosures');

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

        $compiled = $this->compileAndLoadContainer($container, 'testCompiledContainerResolvesSingletons');

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

        $compiled = $this->compileAndLoadContainer($container, 'testCompiledContainerResolvesTransients');

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
        $container->getArgumentMap()->set(TestServiceWithMultipleParams::class, [
            'param1' => 'compiled-override',
            'param2' => 99,
        ]);

        // Bind the service
        $container->bind(TestServiceWithMultipleParams::class);

        // Compile and load container
        $compiled = $this->compileAndLoadContainer($container, 'testCompiledContainerResolvesWithPrimitiveOverrides');

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
        $compiled = $this->compileAndLoadContainer($container, 'testCompiledContainerPreservesTags');

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
    public function testCompiledContainerAppliesPostInterceptor(): void
    {
        $container = new ServiceContainer();
        $container->singleton(Logger::class);
        $container->registerInterceptor(LoggerInterceptor::class);

        $compiled = $this->compileAndLoadContainer($container, 'testCompiledContainerAppliesPostInterceptor');

        /** @var Logger $logger */
        $logger = $compiled->get(Logger::class);

        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertTrue($logger->intercepted, 'Logger instance should be intercepted.');
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testPreInterceptorModifiesParameters(): void
    {
        $container = new ServiceContainer();

        $container->registerInterceptor(PreArgOverride::class);

        $compiled = $this->compileAndLoadContainer($container, 'testPreInterceptorModifiesParameters');

        $instance = $container->get(SimpleService::class);

        $this->assertSame('from-interceptor', $instance->value);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function testCompiledContainerIncludesServiceProviders(): void
    {
        $container = new ServiceContainer();
        $container->registerProvider(DummyProvider::class);

        $compiled = $this->compileAndLoadContainer($container, 'testCompiledContainerIncludesServiceProviders');

        // Ensure service provider is still tagged
        $providers = $compiled->getTagged('service.provider');
        $this->assertNotEmpty($providers, 'Expected at least one tagged service provider');

        $this->assertInstanceOf(DummyProvider::class, $providers[0]);

        // Ensure the service registered by the provider is present
        $this->assertTrue($compiled->has('dummy.service'));
        $this->assertInstanceOf(stdClass::class, $compiled->get('dummy.service'));
    }

    /**
     * @throws ReflectionException
     */
    public function testCompileDoesNotHandleNonInstantiableClassesGracefully(): void
    {
        $this->expectException(ContainerException::class);

        $serviceId = NonInstantiableClass::class;
        $outputFile = __DIR__ . '/../../resources/cache/testCompileDoesNotHandleNonInstantiableClassesGracefully.php';

        $descriptor = $this->createMock(ServiceDescriptorInterface::class);
        $descriptor->method('getConcrete')->willReturn(NonInstantiableClass::class);
        $descriptor->method('isSingleton')->willReturn(true);

        $containerMock = $this->createMock(ServiceContainer::class);
        $containerMock->method('getBindings')->willReturn([$serviceId => $descriptor]);
        $containerMock->method('getArgumentMap')->willReturn(new ArgumentMap());
        $containerMock->method('getTags')->willReturn([]);
        $containerMock->method('getPostInterceptors')->willReturn([]);

        $reflectionMock = $this->createMock(\ReflectionClass::class);
        $reflectionMock->method('isInstantiable')->willReturn(false);

        $reflectionCacheMock = $this->createMock(ReflectionCacheInterface::class);
        $reflectionCacheMock->method('get')->willReturn($reflectionMock);

        $compiler = new ContainerCompiler($containerMock);

        $compiler->compile($outputFile, 'testCompileHandlesNonInstantiableClassesGracefully');

        $this->assertFileExists(
            $outputFile,
            'Compiled file should be created even if some classes are not instantiable.'
        );

        // Load the generated file content
        $generatedCode = file_get_contents($outputFile);

        // Make sure our non-instantiable class is NOT in the compiled services
        $this->assertStringNotContainsString(
            NonInstantiableClass::class,
            $generatedCode,
            'Non-instantiable class should not appear in compiled output.'
        );

        // Cleanup
        @unlink($outputFile);
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function testCompileHandlesDefaultParameterValues(): void
    {
        $container = new ServiceContainer();
        $container->bind(DefaultValueService::class);

        $outputPath = __DIR__ . '/../../resources/cache/testCompileHandlesDefaultParameterValues.php';
        $className = 'testCompileHandlesDefaultParameterValues';
        $namespace = 'Tests\\Integration\\Compiler';

        if (file_exists($outputPath)) {
            unlink($outputPath);
        }

        $compiler = new ContainerCompiler($container);
        $compiler->compile($outputPath, $className, $namespace);

        $this->assertFileExists($outputPath);

        $contents = file_get_contents($outputPath);
        $this->assertStringContainsString("'default-val'", $contents);
    }

    /**
     * @throws ContainerException
     */
    public function testContextualBindingIsHardcoded(): void
    {
        $container = new ServiceContainer();

        // Contextual binding: NeedsLogger gets CustomLogger instead of Logger
        $container->singleton(NeedsLogger::class);

        $container->for(NeedsLogger::class)->set(LoggerInterface::class, CustomLogger::class);

        $compiled = $this->compileAndLoadContainer($container, 'CompiledContainerWithContextual');

        /** @var NeedsLogger $needsLogger */
        $needsLogger = $compiled->get(NeedsLogger::class);

        $this->assertInstanceOf(NeedsLogger::class, $needsLogger);
        $this->assertInstanceOf(CustomLogger::class, $needsLogger->logger);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testCompiledContainerInjectsParameterStoreValues(): void
    {
        $container = new ServiceContainer();

        // Set a parameter directly into the store
        $container->getParameters()->set('config.value', 'compiled-store');

        // Bind the service that depends on the parameter
        $container->singleton(SimpleService::class);

        // Compile and load the container
        $compiled = $this->compileAndLoadContainer($container, 'testCompiledContainerInjectsParameterStoreValues');

        // Grab the instance from the compiled container
        /** @var SimpleService $instance */
        $instance = $compiled->get(SimpleService::class, [
            'value' => $compiled->getParameters()->get('config.value')
        ]);

        $this->assertInstanceOf(SimpleService::class, $instance);
        $this->assertSame('compiled-store', $instance->value, 'Parameter store value should be injected correctly.');
    }
}
