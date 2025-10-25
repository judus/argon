<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Support;

use Maduser\Argon\Container\Support\NullServiceProxy;
use PHPUnit\Framework\TestCase;

final class NullServiceProxyTest extends TestCase
{
    public function testCallDoesNothing(): void
    {
        $proxy = new NullServiceProxy();

        // Should not throw or return anything
        /** @noinspection PhpUndefinedMethodInspection */
        /** @psalm-suppress UndefinedMagicMethod testing null proxy behavior */
        $this->assertNull($proxy->nonExistentMethod());
    }

    public function testGetReturnsNull(): void
    {
        $proxy = new NullServiceProxy();

        /** @noinspection PhpUndefinedFieldInspection */
        /** @psalm-suppress UndefinedMagicPropertyFetch testing null proxy behavior */
        /** @psalm-suppress TypeDoesNotContainNull annotated return type is null */
        $this->assertNull($proxy->someProperty);
    }

    public function testSetDoesNothing(): void
    {
        $proxy = new NullServiceProxy();

        // Set shouldn't throw or persist anything
        /** @noinspection PhpUndefinedFieldInspection */
        /** @psalm-suppress UndefinedMagicPropertyAssignment testing null proxy behavior */
        $proxy->someProperty = 'value';

        /** @psalm-suppress UndefinedMagicPropertyFetch testing null proxy behavior */
        /** @psalm-suppress TypeDoesNotContainNull annotated return type is null */
        $this->assertNull($proxy->someProperty);
    }

    public function testIssetReturnsFalse(): void
    {
        $proxy = new NullServiceProxy();

        $this->assertFalse(isset($proxy->anything));
    }

    public function testUnsetDoesNothing(): void
    {
        $proxy = new NullServiceProxy();

        /** @psalm-suppress UndefinedMagicPropertyFetch testing null proxy behavior */
        unset($proxy->someProperty);

        /** @noinspection PhpUndefinedFieldInspection */
        /** @psalm-suppress UndefinedMagicPropertyFetch testing null proxy behavior */
        /** @psalm-suppress TypeDoesNotContainNull annotated return type is null */
        $this->assertNull($proxy->someProperty);
    }
}
