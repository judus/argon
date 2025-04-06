<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Mocks;

use Tests\Unit\Container\Mocks\SomeEnum;
use Tests\Unit\Container\Mocks\SomeClass;
use Tests\Unit\Container\Mocks\SampleInterface;

class TestSubject
{
    public function primitiveTypes(int $id, string $name, bool $active, float $score): void {}

    public function objectTypes(SomeClass $service, SampleInterface $iface): void {}

    public function nullable(SomeClass $maybe = null, string $optional = 'hello'): void {}

    public function defaults(int $num = 42, string $text = 'yay', bool $flag = true, float $pi = 3.14): void {}

    public function unsupportedDefault(\stdClass $items = new \stdClass()): void {}

    public function closureDefault(callable $cb = null): void {}

    public function closureInstanceDefault(callable $cb = self::DEFAULT_CALLBACK): void {}
    private const DEFAULT_CALLBACK = [self::class, 'someCallback']; // invalid, unsupported

    public function enumDefault(SomeEnum $e = SomeEnum::FOO): void {}

    public function unionTypes(int|SomeClass $id): void {}

    public function unionNoDefault(int|string $value): void {}

    public function unionAllScalars(int|string $id = 42): void {}

    public function unionMultipleObjects(SomeClass|SampleInterface $service): void {}

    public function arrayDefault(array $items = []): void {}

    public function variadicParams(string ...$args): void {}

    public static function someCallback(): void {}
}
