<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Support;

use Maduser\Argon\Container\Support\ReflectionUtils;
use PHPUnit\Framework\TestCase;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Tests\Unit\Container\Mocks\SampleInterface;
use Tests\Unit\Container\Mocks\SomeClass;
use Tests\Unit\Container\Mocks\TestSubject;
use Tests\Unit\Container\Mocks\SomeEnum;

final class ReflectionUtilsTest extends TestCase
{
    public function testPrimitiveTypes(): void
    {
        $result = ReflectionUtils::getMethodParameters(TestSubject::class, 'primitiveTypes');

        $this->assertSame([
            'id' => null,
            'name' => null,
            'active' => null,
            'score' => null,
        ], $result);
    }

    public function testObjectTypes(): void
    {
        $result = ReflectionUtils::getMethodParameters(TestSubject::class, 'objectTypes');

        $this->assertSame([
            'service' => SomeClass::class,
            'iface' => SampleInterface::class,
        ], $result);
    }

    public function testNullableTypesAndDefaults(): void
    {
        $result = ReflectionUtils::getMethodParameters(TestSubject::class, 'nullable');

        $this->assertSame([
            'maybe' => SomeClass::class,
            'optional' => 'hello',
        ], $result);
    }

    public function testDefaults(): void
    {
        $result = ReflectionUtils::getMethodParameters(TestSubject::class, 'defaults');

        $this->assertSame([
            'num' => 42,
            'text' => 'yay',
            'flag' => true,
            'pi' => 3.14,
        ], $result);
    }

    public function testEnumDefaultCompiles(): void
    {
        $result = ReflectionUtils::getMethodParameters(TestSubject::class, 'enumDefault');

        $this->assertSame([
            'e' => 'Tests\Unit\Container\Mocks\SomeEnum::FOO',
        ], $result);
    }

    public function testUnionWithResolvableObject(): void
    {
        $result = ReflectionUtils::getMethodParameters(TestSubject::class, 'unionTypes');

        $this->assertSame([
            'id' => SomeClass::class,
        ], $result);
    }

    public function testUnionAllScalarsCompiles(): void
    {
        $result = ReflectionUtils::getMethodParameters(TestSubject::class, 'unionAllScalars');

        $this->assertSame([
            'id' => 42,
        ], $result);
    }

    public function testUnionWithMultipleObjectsThrows(): void
    {
        $this->expectException(ContainerException::class);
        ReflectionUtils::getMethodParameters(TestSubject::class, 'unionMultipleObjects');
    }

    public function testUnionWithoutResolvableThrows(): void
    {
        $this->expectException(ContainerException::class);
        ReflectionUtils::getMethodParameters(TestSubject::class, 'unionNoDefault');
    }

    public function testUnsupportedDefaultInstanceThrows(): void
    {
        $this->expectException(ContainerException::class);
        ReflectionUtils::getMethodParameters(TestSubject::class, 'unsupportedDefault');
    }

    public function testClosureWithNullDefaultIsAllowed(): void
    {
        $result = ReflectionUtils::getMethodParameters(TestSubject::class, 'closureDefault');

        $this->assertSame(['cb' => null], $result);
    }

    public function testClosureWithInstanceDefaultThrows(): void
    {
        $this->expectException(ContainerException::class);
        ReflectionUtils::getMethodParameters(TestSubject::class, 'closureInstanceDefault');
    }

    public function testArrayDefaultThrows(): void
    {
        $this->expectException(ContainerException::class);
        ReflectionUtils::getMethodParameters(TestSubject::class, 'arrayDefault');
    }

    public function testVariadicParamsThrows(): void
    {
        $this->expectException(ContainerException::class);
        ReflectionUtils::getMethodParameters(TestSubject::class, 'variadicParams');
    }
}
