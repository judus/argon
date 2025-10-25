<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Interceptors;

use Maduser\Argon\Container\Interceptors\Post\Contracts\ValidationInterface;
use Maduser\Argon\Container\Interceptors\Post\ValidationInterceptor;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Unit\Container\Mocks\MockValidationClass;

final class ValidationInterceptorTest extends TestCase
{
    public function testSupportsReturnsTrueForObjectImplementingInterface(): void
    {
        $instance = $this->createMock(ValidationInterface::class);
        $this->assertTrue(ValidationInterceptor::supports($instance));
    }

    public function testSupportsReturnsTrueForClassStringImplementingInterface(): void
    {
        $this->assertTrue(ValidationInterceptor::supports(MockValidationClass::class));
    }

    public function testSupportsReturnsFalseForNonMatchingObject(): void
    {
        $this->assertFalse(ValidationInterceptor::supports(new stdClass()));
    }

    public function testSupportsReturnsFalseForClassStringNotImplementingInterface(): void
    {
        $this->assertFalse(ValidationInterceptor::supports(stdClass::class));
    }

    public function testInterceptCallsValidateOnValidInstance(): void
    {
        $instance = $this->createMock(ValidationInterface::class);
        $instance->expects($this->once())->method('validate');

        $interceptor = new ValidationInterceptor();
        $interceptor->intercept($instance);
    }

    public function testInterceptSkipsNonValidationInstances(): void
    {
        $interceptor = new ValidationInterceptor();

        // Just to confirm it doesn't blow up
        $interceptor->intercept(new stdClass());

        $this->assertTrue(true); // no exceptions, we're good
    }
}
