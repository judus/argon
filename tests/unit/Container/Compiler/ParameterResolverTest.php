<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Compiler;

use PHPUnit\Framework\TestCase;
use ReflectionNamedType;
use ReflectionParameter;
use Tests\Integration\Mocks\Logger;
use Tests\Mocks\LoggerInterface;
use Tests\Unit\Container\Compiler\Mocks\FakeResolver;

final class ParameterResolverTest extends TestCase
{
    public function testReturnsArgumentAccessWhenNoFallbacks(): void
    {
        $param = $this->makeParameterMock('id', allowsNull: false, hasDefault: false);
        $resolver = new FakeResolver();

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
        $resolver = new FakeResolver();

        $code = $resolver->resolveParameter($param, 'NoBindings');
        $this->assertSame("array_key_exists('logger', \$args) ? \$args['logger'] : null", $code);
    }

    public function testUsesContextualBindingIfAvailable(): void
    {
        $param = $this->makeParameterMock('service', LoggerInterface::class);
        $resolver = new FakeResolver(['SomeService' => [
            LoggerInterface::class => Logger::class
        ]]);

        $code = $resolver->resolveParameter($param, 'SomeService');
        $this->assertSame(
            "array_key_exists('service', \$args) ? \$args['service'] : " .
            "\$this->get('Tests\Integration\Mocks\Logger')",
            $code
        );
    }

    public function testUsesContainerIfServiceExists(): void
    {
        $param = $this->makeParameterMock('logger', Logger::class);
        $resolver = new FakeResolver();
        $resolver->container->hasServices[] = Logger::class;

        $code = $resolver->resolveParameter($param, 'AnyService');
        $this->assertSame(
            "array_key_exists('logger', \$args) ? \$args['logger'] : \$this->get('" . Logger::class . "')",
            $code
        );
    }

    public function testResolvesDescriptorArgumentValue(): void
    {
        $param = $this->makeParameterMock('foo', allowsNull: false, hasDefault: false);
        $resolver = new FakeResolver();
        $resolver->container->descriptorArgs = ['foo' => 123];

        $code = $resolver->resolveParameter($param, 'ServiceId');
        $this->assertSame("array_key_exists('foo', \$args) ? \$args['foo'] : 123", $code);
    }

    public function testResolvesDescriptorArgumentAsClass(): void
    {
        $param = $this->makeParameterMock('foo', Logger::class, isBuiltin: false);
        $resolver = new FakeResolver();
        $resolver->container->descriptorArgs = ['foo' => Logger::class];

        $code = $resolver->resolveParameter($param, 'ServiceId');
        $this->assertSame(
            "array_key_exists('foo', \$args) ? \$args['foo'] : \$this->get('" . Logger::class . "')",
            $code
        );
    }

    public function testReturnsDefaultValue(): void
    {
        $param = $this->makeParameterMock('foo', hasDefault: true, defaultValue: 'default');
        $resolver = new FakeResolver();

        $code = $resolver->resolveParameter($param, 'ServiceId');
        $this->assertSame("array_key_exists('foo', \$args) ? \$args['foo'] : 'default'", $code);
    }

    // === Helpers ===

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
        $param->method('isDefaultValueAvailable')->willReturn($hasDefault);
        if ($hasDefault) {
            $param->method('getDefaultValue')->willReturn($defaultValue);
        }

        return $param;
    }
}
