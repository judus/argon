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
        $param = $this->mockParam('param1', null, '\Tests\Unit\Container\Mocks\SomeClass');

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
        $param = $this->mockParam('param2', null, 'Tests\Unit\Container\Mocks\SomeClass');

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
        $param = $this->mockParam('dependency', stdClass::class, 'Tests\Unit\Container\Mocks\MyConsumer');

        $this->registry->method('get')->willReturn([]);
        $this->bindings->method('has')->with('Tests\Unit\Container\Mocks\MyConsumer', stdClass::class)->willReturn(true);
        $this->contextual->expects($this->once())
            ->method('resolve')
            ->with('Tests\Unit\Container\Mocks\MyConsumer', stdClass::class)
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
        $param = $this->mockParam('service', stdClass::class, 'Tests\Unit\Container\Mocks\ServiceConsumer');

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
        $param = $this->mockParam('missingResolver', stdClass::class, 'Tests\Unit\Container\Mocks\AppThing');

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
            'Tests\Unit\Container\Mocks\SomeClass',
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
            'Tests\Unit\Container\Mocks\ServiceX',
            isBuiltin: true,
            isOptional: false
        );

        $this->registry->method('get')->willReturn([]);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            "Cannot resolve primitive parameter 'primitive' in service 'Tests\\Unit\\Container\\Mocks\\ServiceX'."
        );
        $this->resolver->resolve($param);
    }

    /**
     * Utility to mock a ReflectionParameter with type metadata
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
