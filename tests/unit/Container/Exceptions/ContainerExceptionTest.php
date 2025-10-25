<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Exceptions;

use Maduser\Argon\Container\Exceptions\ContainerException;
use PHPUnit\Framework\TestCase;

final class ContainerExceptionTest extends TestCase
{
    public function testFromInternalError(): void
    {
        $exception = ContainerException::fromInternalError('Something failed');

        $this->assertInstanceOf(ContainerException::class, $exception);
        $this->assertStringContainsString('Something failed', $exception->getMessage());
    }
}
