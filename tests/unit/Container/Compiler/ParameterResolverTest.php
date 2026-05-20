<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Compiler;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Compiler\ParameterExpressionResolver;
use PHPUnit\Framework\TestCase;
use ReflectionNamedType;
use ReflectionParameter;
use Tests\Integration\Mocks\Logger;
use Tests\Mocks\LoggerInterface;

final class ParameterResolverTest extends TestCase
{
    public function testReturnsArgumentAccessWhenNoFallbacks(): void
    {
        $param = $this->makeParameterMock('id', allowsNull: false, hasDefault: false);
        $resolver = $this->makeResolver();

        $code = $resolver->resolveParameter($param, 'SomeService');
        $this->assertSame(
            "array_key_exists('id', \$args) ? \$args['id'] : " .
            "throw ContainerException::fromServiceId('SomeService', 'Missing required argument \\'id\\'')",
            $code
        );
    }

    public function testReturnsNullFallbackIfAllowedAndNothingElse(): void
    {
        $param = $this->makeParameterMock('logger', allowsNull: true, hasDefault: false);
        $resolver = $this->makeResolver();

        $code = $resolver->resolveParameter($param, 'NoBindings');
        $this->assertSame("array_key_exists('logger', \$args) ? \$args['logger'] : null", $code);
    }

    public function testUsesContextualBindingIfAvailable(): void
    {
        $param = $this->makeParameterMock('service', LoggerInterface::class, isBuiltin: false);
        $container = new ArgonContainer();
        $container->for('SomeService')->set(LoggerInterface::class, Logger::class);
        $resolver = $this->makeResolver($container);

        $code = $resolver->resolveParameter($param, 'SomeService');
        $this->assertSame(
            "array_key_exists('service', \$args) ? " .
            "(is_string(\$args['service']) && is_a(\$args['service'], " .
            var_export(LoggerInterface::class, true) .
            ", true) ? " .
            "\$this->get(\$args['service']) : \$args['service']) : " .
            "\$this->get(" . var_export(Logger::class, true) . ")",
            $code
        );
    }

    public function testUsesObjectFallbackForNonNullableClass(): void
    {
        $param = $this->makeParameterMock('logger', Logger::class, isBuiltin: false);
        $resolver = $this->makeResolver();

        $code = $resolver->resolveParameter($param, 'AnyService');
        $this->assertSame(
            "array_key_exists('logger', \$args) ? " .
            "(is_string(\$args['logger']) && is_a(\$args['logger'], " .
            var_export(Logger::class, true) .
            ", true) ? " .
            "\$this->get(\$args['logger']) : \$args['logger']) : " .
            "\$this->get(" . var_export(Logger::class, true) . ")",
            $code
        );
    }

    public function testResolvesDescriptorArgumentValue(): void
    {
        $param = $this->makeParameterMock('foo', allowsNull: false, hasDefault: false);
        $container = new ArgonContainer();
        $container->set(Logger::class, args: ['foo' => 123]);
        $resolver = $this->makeResolver($container);

        $code = $resolver->resolveParameter($param, Logger::class);
        $this->assertSame("array_key_exists('foo', \$args) ? \$args['foo'] : 123", $code);
    }

    public function testResolvesDescriptorArgumentAsClass(): void
    {
        $param = $this->makeParameterMock('foo', Logger::class, isBuiltin: false);
        $container = new ArgonContainer();
        $container->set(Logger::class, args: ['foo' => Logger::class]);
        $resolver = $this->makeResolver($container);

        $code = $resolver->resolveParameter($param, Logger::class);
        $this->assertSame(
            "array_key_exists('foo', \$args) ? " .
            "(is_string(\$args['foo']) && is_a(\$args['foo'], " .
            var_export(Logger::class, true) .
            ", true) ? " .
            "\$this->get(\$args['foo']) : \$args['foo']) : " .
            "\$this->get(" . var_export(Logger::class, true) . ")",
            $code
        );
    }

    public function testPreservesExplicitNullDescriptorArgument(): void
    {
        $param = $this->makeParameterMock('foo', allowsNull: true);
        $container = new ArgonContainer();
        $container->set(Logger::class, args: ['foo' => null]);
        $resolver = $this->makeResolver($container);

        $code = $resolver->resolveParameter($param, Logger::class);
        $this->assertSame("array_key_exists('foo', \$args) ? \$args['foo'] : NULL", $code);
    }

    public function testReturnsDefaultValue(): void
    {
        $param = $this->makeParameterMock('foo', hasDefault: true, defaultValue: 'default');
        $resolver = $this->makeResolver();

        $code = $resolver->resolveParameter($param, 'ServiceId');
        $this->assertSame("array_key_exists('foo', \$args) ? \$args['foo'] : 'default'", $code);
    }

    // === Helpers ===

    private function makeResolver(?ArgonContainer $container = null): ParameterExpressionResolver
    {
        $container ??= new ArgonContainer();

        return new ParameterExpressionResolver($container, $container->getContextualBindings());
    }

    private function makeParameterMock(
        string $name,
        string $typeName = 'string',
        bool $allowsNull = false,
        bool $isBuiltin = true,
        bool $hasDefault = false,
        mixed $defaultValue = null
    ): ReflectionParameter {
        $type = $this->createMock(ReflectionNamedType::class);
        $type->method('getName')->willReturn($typeName);
        $type->method('allowsNull')->willReturn($allowsNull);
        $type->method('isBuiltin')->willReturn($isBuiltin);

        $param = $this->createMock(ReflectionParameter::class);
        $param->method('getName')->willReturn($name);
        $param->method('getType')->willReturn($type);
        $param->method('getDeclaringClass')->willReturn(null);
        $param->method('allowsNull')->willReturn($allowsNull);
        $param->method('isDefaultValueAvailable')->willReturn($hasDefault);
        if ($hasDefault) {
            $param->method('getDefaultValue')->willReturn($defaultValue);
        }

        return $param;
    }
}
