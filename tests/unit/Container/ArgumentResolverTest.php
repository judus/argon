<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\ArgumentResolver;
use Maduser\Argon\Container\ContextualBindings;
use Maduser\Argon\Container\Contracts\ArgumentMapInterface;
use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use Maduser\Argon\Container\Contracts\ContextualResolverInterface;
use Maduser\Argon\Container\Contracts\ServiceResolverInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use stdClass;
use Tests\Unit\Container\Mocks\AppThing;
use Tests\Unit\Container\Mocks\Logger;
use Tests\Unit\Container\Mocks\MyConsumer;
use Tests\Unit\Container\Mocks\ServiceConsumer;
use Tests\Unit\Container\Mocks\ServiceX;
use Tests\Unit\Container\Mocks\SomeClass;
use Tests\Unit\Container\Mocks\UnionExample;

final class ArgumentResolverTest extends TestCase
{
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ArgumentMapInterface&MockObject $arguments;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ContextualResolverInterface&MockObject $contextual;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ContextualBindingsInterface&MockObject $contextualBindings;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ArgumentResolver $resolver;

    #[\Override]
    protected function setUp(): void
    {
        $this->arguments = $this->createMock(ArgumentMapInterface::class);
        $this->contextual = $this->createMock(ContextualResolverInterface::class);
        $this->contextualBindings = $this->createMock(ContextualBindingsInterface::class);

        $this->resolver = new ArgumentResolver(
            $this->contextual,
            $this->arguments,
            $this->contextualBindings
        );
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testResolveReturnsOverride(): void
    {
        $param = $this->mockParam('param1', null, SomeClass::class);

        $this->arguments->expects($this->once())
            ->method('get')
            ->with(SomeClass::class)
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

        $this->arguments->method('get')
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
        $param = $this->mockParam(
            name: 'dependency',
            type: stdClass::class,
            declaringClass: MyConsumer::class,
        );

        $this->arguments->method('get')->willReturn([]);

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

    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testResolveFallsBackToServiceResolver(): void
    {
        $param = $this->mockParam('service', stdClass::class, ServiceConsumer::class);

        $this->arguments->method('get')->willReturn([]);
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

    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testThrowsWhenTryingToResolveClassWithoutInjectedServiceResolver(): void
    {
        $param = $this->mockParam('missingResolver', stdClass::class, AppThing::class);

        $this->arguments->method('get')->willReturn([]);
        $this->contextualBindings->method('has')->willReturn(false);

        $this->expectException(ContainerException::class);
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

        $this->arguments->method('getArgument')->willReturn([]);

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
        );

        $this->arguments->method('getArgument')->willReturn([]);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            "Cannot resolve primitive parameter 'primitive' in service '" . ServiceX::class . "'."
        );

        $this->resolver->resolve($param);
    }

    /**
     * @psalm-param class-string $declaringClass
     * @psalm-param 42|null $defaultValue
     *
     * @throws ReflectionException
     */
    private function mockParam(
        string $name,
        ?string $type,
        string $declaringClass,
        bool $isBuiltin = false,
        bool $isOptional = false,
        int|null $defaultValue = null
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

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testUnionTypeWithSingleContextualBindingResolvesSuccessfully(): void
    {
        $contextualResolver = $this->createMock(ContextualResolverInterface::class);
        $contextualBindings = new ContextualBindings();
        $dummyMap = $this->createStub(ArgumentMapInterface::class);

        // Real contextual binding setup
        $contextualBindings->bind(UnionExample::class, Logger::class, Logger::class);

        // Expect contextual resolver to be used
        $contextualResolver->expects($this->once())
            ->method('resolve')
            ->with(UnionExample::class, Logger::class)
            ->willReturn(new Logger());

        $resolver = new ArgumentResolver($contextualResolver, $dummyMap, $contextualBindings);

        if ($constructor = (new ReflectionClass(UnionExample::class))->getConstructor()) {
            $result = $resolver->resolve($constructor->getParameters()[0]);
        } else {
            $result = null;
        }

        $this->assertInstanceOf(Logger::class, $result);
    }
}
