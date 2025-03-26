<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use Maduser\Argon\Container\Contracts\ContextualResolverInterface;
use Maduser\Argon\Container\Contracts\ParameterRegistryInterface;
use Maduser\Argon\Container\Contracts\ServiceResolverInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ParameterResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use stdClass;
use Tests\Unit\Container\Mocks\AppThing;
use Tests\Unit\Container\Mocks\MyConsumer;
use Tests\Unit\Container\Mocks\ServiceConsumer;
use Tests\Unit\Container\Mocks\ServiceX;
use Tests\Unit\Container\Mocks\SomeClass;

class ParameterResolverTest extends TestCase
{
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ParameterRegistryInterface&MockObject $registry;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ContextualResolverInterface&MockObject $contextual;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ContextualBindingsInterface&MockObject $bindings;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ParameterResolver $resolver;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ParameterRegistryInterface::class);
        $this->contextual = $this->createMock(ContextualResolverInterface::class);
        $this->bindings = $this->createMock(ContextualBindingsInterface::class);

        $this->resolver = new ParameterResolver($this->contextual, $this->registry, $this->bindings);
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testResolveReturnsOverride(): void
    {
        $param = $this->mockParam('param1', null, SomeClass::class);

        $this->registry->expects($this->once())
            ->method('get')
            ->with('Tests\Unit\Container\Mocks\SomeClass')
            ->willReturn(['param1' => 'value-from-registry']);

        $result = $this->resolver->resolve($param, ['param1' => 'override-value']);

        $this->assertSame('override-value', $result);
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testResolveReturnsRegistryValue(): void
    {
        $param = $this->mockParam('param2', null, SomeClass::class);

        $this->registry->method('get')
            ->willReturn(['param2' => 'value-from-registry']);

        $result = $this->resolver->resolve($param);

        $this->assertSame('value-from-registry', $result);
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testResolveReturnsContextualBinding(): void
    {
        $param = $this->mockParam('dependency', stdClass::class, MyConsumer::class);

        $this->registry->method('get')->willReturn([]);
        $this->bindings->method('has')
            ->with(MyConsumer::class, stdClass::class)
            ->willReturn(true);
        $this->contextual->expects($this->once())
            ->method('resolve')
            ->with(MyConsumer::class, stdClass::class)
            ->willReturn(new stdClass());

        $result = $this->resolver->resolve($param);

        $this->assertInstanceOf(stdClass::class, $result);
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testResolveFallsBackToServiceResolver(): void
    {
        $param = $this->mockParam('service', stdClass::class, ServiceConsumer::class);

        $this->registry->method('get')->willReturn([]);
        $this->bindings->method('has')->willReturn(false);

        $serviceResolver = $this->createMock(ServiceResolverInterface::class);
        $this->resolver->setServiceResolver($serviceResolver);

        $serviceResolver->expects($this->once())
            ->method('resolve')
            ->with(stdClass::class)
            ->willReturn(new stdClass());

        $result = $this->resolver->resolve($param);

        $this->assertInstanceOf(stdClass::class, $result);
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testResolveThrowsWhenNoServiceResolver(): void
    {
        $param = $this->mockParam('missingResolver', stdClass::class, AppThing::class);

        $this->registry->method('get')->willReturn([]);
        $this->bindings->method('has')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("ParameterResolver: missing ServiceResolver.");

        $this->resolver->resolve($param);
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testResolveReturnsDefaultForOptionalPrimitive(): void
    {
        $param = $this->mockParam(
            'limit',
            'int',
            SomeClass::class,
            isBuiltin: true,
            isOptional: true,
            defaultValue: 42
        );

        $this->registry->method('get')->willReturn([]);

        $result = $this->resolver->resolve($param);

        $this->assertSame(42, $result);
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     */
    public function testResolveThrowsForRequiredPrimitiveWithoutDefault(): void
    {
        $param = $this->mockParam(
            'primitive',
            'string',
            ServiceX::class,
            isBuiltin: true,
            isOptional: false
        );

        $this->registry->method('get')->willReturn([]);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            "Cannot resolve primitive parameter 'primitive' in service '" . ServiceX::class . "'."
        );
        $this->resolver->resolve($param);
    }

    /**
     * Utility to mock a ReflectionParameter with type metadata
     *
     * @param class-string $declaringClass The FQCN of the declaring class.
     *
     * @throws ReflectionException
     */
    private function mockParam(
        string $name,
        ?string $type,
        string $declaringClass,
        bool $isBuiltin = false,
        bool $isOptional = false,
        mixed $defaultValue = null
    ): ReflectionParameter {
        $typeMock = null;

        if ($type !== null) {
            $typeMock = $this->createMock(ReflectionNamedType::class);
            $typeMock->method('getName')->willReturn($type);
            $typeMock->method('isBuiltin')->willReturn($isBuiltin);
        }

        $param = $this->createMock(ReflectionParameter::class);
        $param->method('getName')->willReturn($name);
        $param->method('getType')->willReturn($typeMock);
        $param->method('getDeclaringClass')->willReturn(new ReflectionClass($declaringClass));
        $param->method('isOptional')->willReturn($isOptional);

        if ($isOptional) {
            $param->method('getDefaultValue')->willReturn($defaultValue);
        }

        return $param;
    }
}
