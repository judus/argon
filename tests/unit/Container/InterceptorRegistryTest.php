<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\InterceptorRegistry;
use Maduser\Argon\Container\Exceptions\ContainerException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Unit\Container\Stubs\NonMatchingInterceptor;
use Tests\Unit\Container\Stubs\StubInterceptor;

class InterceptorRegistryTest extends TestCase
{
    public function testAllReturnsEmptyArrayInitially(): void
    {
        $registry = new InterceptorRegistry();

        $this->assertSame([], $registry->allPost());
    }

    /**
     * @throws ContainerException
     */
    public function testRegisterAddsValidInterceptor(): void
    {
        $registry = new InterceptorRegistry();

        $registry->registerPost(StubInterceptor::class);

        $this->assertContains(StubInterceptor::class, $registry->allPost());
    }

    /**
     * @psalm-suppress ArgumentTypeCoercion
     * @psalm-suppress UndefinedClass
     */
    public function testRegisterThrowsIfClassDoesNotExist(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Interceptor class 'NonExistentClass' does not exist.");

        $registry = new InterceptorRegistry();
        $registry->registerPost('NonExistentClass');
    }

    public function testRegisterThrowsIfClassDoesNotImplementInterface(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("[stdClass] Interceptor 'stdClass' must implement PostResolutionInterceptorInterface.");

        $registry = new InterceptorRegistry();
        /** @psalm-suppress InvalidArgument intended */
        $registry->registerPost(stdClass::class);
    }

    /**
     * @throws ContainerException
     */
    public function testApplyRunsInterceptorIfSupported(): void
    {
        $registry = new InterceptorRegistry();
        $registry->registerPost(StubInterceptor::class);

        $object = new stdClass();
        $this->assertFalse(isset($object->intercepted));

        $result = $registry->matchPost($object);

        $this->assertTrue($result->intercepted);
    }

    /**
     * @throws ContainerException
     */
    public function testApplySkipsInterceptorIfNotSupported(): void
    {
        $registry = new InterceptorRegistry();
        $registry->registerPost(NonMatchingInterceptor::class);

        $object = new stdClass();
        $result = $registry->matchPost($object);

        $this->assertObjectNotHasProperty('intercepted', $result);
    }
}
