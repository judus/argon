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
    private ParameterRegistryInterface&MockObject $registry;
    private ContextualResolverInterface&MockObject $contextual;
    private ContextualBindingsInterface&MockObject $contextualBindings;
    private ParameterResolver $resolver;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ParameterRegistryInterface::class);
        $this->contextual = $this->createMock(ContextualResolverInterface::class);
        $this->contextualBindings = $this->createMock(ContextualBindingsInterface::class);

        $this->resolver = new ParameterResolver(
            $this->contextual,
            $this->registry,
            $this->contextualBindings
        );
    }

    public function testResolveReturnsOverride(): void
    {
        $param = $this->mockParam('param1', null, SomeClass::class);

        $this->registry->expects($this->once())
            ->method('getScope')
            ->with(SomeClass::class)
            ->willReturn(['param1' => 'value-from-registry']);

        $result = $this->resolver->resolve($param, ['param1' => 'override-value']);

        $this->assertSame('override-value', $result);
    }

    public function testResolveReturnsRegistryValue(): void
    {
        $param = $this->mockParam('param2', null, SomeClass::class);

        $this->registry->method('getScope')
            ->willReturn(['param2' => 'value-from-registry']);

        $result = $this->resolver->resolve($param);

        $this->assertSame('value-from-registry', $result);
    }

    public function testResolveReturnsContextualBinding(): void
    {
        $param = $this->mockParam(
            name: 'dependency',
            type: stdClass::class,
            declaringClass: MyConsumer::class,
            isBuiltin: false
        );

        $this->registry->method('getScope')->willReturn([]);

        $this->contextualBindings->method('has')
            ->with(MyConsumer::class, stdClass::class)
            ->willReturn(true);

        $this->contextual->expects($this->once())
            ->method('resolve')
            ->with(MyConsumer::class, stdClass::class)
            ->willReturn(new stdClass());

        $this->resolver->setServiceResolver(
            $this->createMock(ServiceResolverInterface::class)
        );

        $result = $this->resolver->resolve($param);

        $this->assertInstanceOf(stdClass::class, $result);
    }

    public function testResolveFallsBackToServiceResolver(): void
    {
        $param = $this->mockParam('service', stdClass::class, ServiceConsumer::class, isBuiltin: false);

        $this->registry->method('getScope')->willReturn([]);
        $this->contextualBindings->method('has')->willReturn(false);

        $serviceResolver = $this->createMock(ServiceResolverInterface::class);
        $this->resolver->setServiceResolver($serviceResolver);

        $serviceResolver->expects($this->once())
            ->method('resolve')
            ->with(stdClass::class)
            ->willReturn(new stdClass());

        $result = $this->resolver->resolve($param);

        $this->assertInstanceOf(stdClass::class, $result);
    }

    public function testThrowsWhenTryingToResolveClassWithoutInjectedServiceResolver(): void
    {
        $param = $this->mockParam('missingResolver', stdClass::class, AppThing::class, isBuiltin: false);

        $this->registry->method('getScope')->willReturn([]);
        $this->contextualBindings->method('has')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("ParameterResolver: missing ServiceResolver.");

        $this->resolver->resolve($param);
    }

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

        $this->registry->method('getScoped')->willReturn([]);

        $result = $this->resolver->resolve($param);

        $this->assertSame(42, $result);
    }

    public function testResolveThrowsForRequiredPrimitiveWithoutDefault(): void
    {
        $param = $this->mockParam(
            'primitive',
            'string',
            ServiceX::class,
            isBuiltin: true,
            isOptional: false
        );

        $this->registry->method('getScoped')->willReturn([]);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            "Cannot resolve primitive parameter 'primitive' in service '" . ServiceX::class . "'."
        );

        $this->resolver->resolve($param);
    }

    /**
     * @psalm-param class-string $declaringClass
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
            $param->method('isDefaultValueAvailable')->willReturn(true);
        } else {
            $param->method('isDefaultValueAvailable')->willReturn(false);
        }

        return $param;
    }
}
