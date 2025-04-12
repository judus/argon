<?php

declare(strict_types=1);

namespace Tests\Integration;

use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ArgonContainer;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Mocks\A;
use Tests\Integration\Mocks\Bar;
use Tests\Integration\Mocks\DeepGraph;
use Tests\Integration\Mocks\HasAmbiguousUnion;
use Tests\Integration\Mocks\HasMixedParam;
use Tests\Integration\Mocks\Logger;
use Tests\Integration\Mocks\MidLevel;
use Tests\Integration\Mocks\MixedParamWithDefault;
use Tests\Integration\Mocks\NeedsNullable;
use Tests\Integration\Mocks\NeedsScalar;
use Tests\Integration\Mocks\NeedsUnknown;
use Tests\Integration\Mocks\NullableMixedParam;
use Tests\Integration\Mocks\UnresolvableMixedParam;
use Tests\Integration\Mocks\UsesLogger;
use Tests\Integration\Mocks\WithDefaults;

final class AutowiringTest extends TestCase
{
    public function testAutowiresSingleClassDependency(): void
    {
        $container = new ArgonContainer();

        $instance = $container->get(UsesLogger::class);

        $this->assertInstanceOf(UsesLogger::class, $instance);
        $this->assertInstanceOf(Logger::class, $instance->logger);
    }

    public function testAutowiresDeepDependencyTree(): void
    {
        $container = new ArgonContainer();

        $instance = $container->get(DeepGraph::class);

        $this->assertInstanceOf(DeepGraph::class, $instance);
        $this->assertInstanceOf(MidLevel::class, $instance->mid);
        $this->assertInstanceOf(Logger::class, $instance->mid->logger);
    }

    /**
     * @throws ContainerException
     */
    public function testThrowsForMissingDependency(): void
    {
        $container = new ArgonContainer();

        $this->expectException(NotFoundException::class);
        $container->get(NeedsUnknown::class);
    }

    public function testThrowsForCircularDependency(): void
    {
        $container = new ArgonContainer();

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/circular dependency/i');

        $container->get(A::class);
    }

    /**
     * @throws NotFoundException
     */
    public function testThrowsForUnresolvableScalarParameter(): void
    {
        $container = new ArgonContainer();

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/primitive parameter/');

        $container->get(NeedsScalar::class);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testNullableDependencyGetsResolvedIfResolvable(): void
    {
        $container = new ArgonContainer();
        $instance = $container->get(NeedsNullable::class);

        $this->assertInstanceOf(Logger::class, $instance->logger);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testDefaultsAreAppliedToOptionalConstructorArguments(): void
    {
        $container = new ArgonContainer();
        $instance = $container->get(WithDefaults::class);

        $this->assertInstanceOf(WithDefaults::class, $instance);
        $this->assertSame('default', $instance->value);
    }

    /**
     * @throws NotFoundException
     */
    public function testThrowsOnUnresolvableMixedParameter(): void
    {
        $container = new ArgonContainer();

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/mixed/i');

        $container->get(UnresolvableMixedParam::class);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testResolvesMixedParameterWithDefaultValue(): void
    {
        $container = new ArgonContainer();

        $instance = $container->get(MixedParamWithDefault::class);

        $this->assertInstanceOf(MixedParamWithDefault::class, $instance);
        $this->assertSame('foo', $instance->data);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testResolvesMixedParameterWithNullAllowed(): void
    {
        $container = new ArgonContainer();

        $instance = $container->get(NullableMixedParam::class);

        $this->assertInstanceOf(NullableMixedParam::class, $instance);
        $this->assertNull($instance->data);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testThrowsOnAmbiguousResolvableUnionTypes(): void
    {
        $container = new ArgonContainer();

        // Both Logger and Bar are resolvable => ambiguous
        $container->set(Logger::class);
        $container->set(Bar::class);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/ambiguous union type/i');

        $container->get(HasAmbiguousUnion::class);
    }
}
