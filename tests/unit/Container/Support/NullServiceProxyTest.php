<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Support;

use Maduser\Argon\Container\Support\NullServiceProxy;
use PHPUnit\Framework\TestCase;

class NullServiceProxyTest extends TestCase
{
    public function testCallDoesNothing(): void
    {
        $proxy = new NullServiceProxy();

        // Should not throw or return anything
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertNull($proxy->nonExistentMethod());
    }

    public function testGetReturnsNull(): void
    {
        $proxy = new NullServiceProxy();

        /** @noinspection PhpUndefinedFieldInspection */
        $this->assertNull($proxy->someProperty);
    }

    public function testSetDoesNothing(): void
    {
        $proxy = new NullServiceProxy();

        // Set shouldn't throw or persist anything
        /** @noinspection PhpUndefinedFieldInspection */
        $proxy->someProperty = 'value';

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

        unset($proxy->someProperty);

        /** @noinspection PhpUndefinedFieldInspection */
        $this->assertNull($proxy->someProperty);
    }
}
