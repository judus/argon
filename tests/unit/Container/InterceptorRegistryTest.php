<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;
use Maduser\Argon\Container\Contracts\PreResolutionInterceptorInterface;
use Maduser\Argon\Container\Contracts\ServiceResolverInterface;
use Maduser\Argon\Container\InterceptorRegistry;
use Maduser\Argon\Container\Exceptions\ContainerException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Unit\Container\Stubs\NonMatchingInterceptor;
use Tests\Unit\Container\Stubs\StubInterceptor;
use Tests\Unit\Container\Stubs\StubPreInterceptor;

final class InterceptorRegistryTest extends TestCase
{
    public function testAllPostReturnsEmptyArrayInitially(): void
    {
        $registry = new InterceptorRegistry();
        $this->assertSame([], $registry->allPost());
    }

    public function testAllPreReturnsEmptyArrayInitially(): void
    {
        $registry = new InterceptorRegistry();
        $this->assertSame([], $registry->allPre());
    }

    /**
     * @throws ContainerException
     */
    public function testRegisterPostAddsValidInterceptor(): void
    {
        $registry = new InterceptorRegistry();
        $registry->registerPost(StubInterceptor::class);

        $this->assertContains(StubInterceptor::class, $registry->allPost());
    }

    /**
     * @throws ContainerException
     */
    public function testRegisterPreAddsValidInterceptor(): void
    {
        $registry = new InterceptorRegistry();
        $registry->registerPre(StubPreInterceptor::class);

        $this->assertContains(StubPreInterceptor::class, $registry->allPre());
    }

    public function testRegisterPostThrowsIfClassDoesNotExist(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Interceptor class 'NonExistentClass' does not exist.");

        $registry = new InterceptorRegistry();
        /** @var class-string<PostResolutionInterceptorInterface> $invalid */
        $invalid = 'NonExistentClass';
        $registry->registerPost($invalid);
    }

    public function testRegisterPreThrowsIfClassDoesNotExist(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Interceptor class 'NonExistentClass' does not exist.");

        $registry = new InterceptorRegistry();
        /** @var class-string<PreResolutionInterceptorInterface> $invalid */
        $invalid = 'NonExistentClass';
        $registry->registerPre($invalid);
    }

    public function testRegisterPostThrowsIfClassDoesNotImplementInterface(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            "[stdClass] Interceptor 'stdClass' must implement PostResolutionInterceptorInterface."
        );

        $registry = new InterceptorRegistry();
        /** @psalm-suppress InvalidArgument */
        $registry->registerPost(stdClass::class);
    }

    public function testRegisterPreThrowsIfClassDoesNotImplementInterface(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            "[stdClass] Interceptor 'stdClass' must implement PreResolutionInterceptorInterface."
        );

        $registry = new InterceptorRegistry();
        /** @psalm-suppress InvalidArgument */
        $registry->registerPre(stdClass::class);
    }

    /**
     * @throws ContainerException
     */
    public function testMatchPostRunsInterceptorIfSupported(): void
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
    public function testMatchPostSkipsInterceptorIfNotSupported(): void
    {
        $registry = new InterceptorRegistry();
        $registry->registerPost(NonMatchingInterceptor::class);

        $object = new stdClass();
        $result = $registry->matchPost($object);

        $this->assertObjectNotHasProperty('intercepted', $result);
    }

    /**
     * @throws ContainerException
     */
    public function testMatchPreReturnsMatchingInterceptor(): void
    {
        $registry = new InterceptorRegistry();
        $registry->registerPre(StubPreInterceptor::class);

        $result = $registry->matchPre('StubMatch');

        $this->assertInstanceOf(stdClass::class, $result);
    }

    /**
     * @throws ContainerException
     */
    public function testMatchPreReturnsNullIfNoInterceptorMatches(): void
    {
        $registry = new InterceptorRegistry();
        $registry->registerPre(StubPreInterceptor::class);

        $result = $registry->matchPre('UnmatchedService');

        $this->assertNull($result);
    }

    public function testMatchPostThrowsWhenResolverReturnsInvalidInstance(): void
    {
        $resolver = $this->createMock(ServiceResolverInterface::class);
        $resolver->method('resolve')->willReturn(new stdClass());

        $registry = new InterceptorRegistry();
        $registry->setResolver($resolver);
        $registry->registerPost(StubInterceptor::class);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Resolved interceptor must implement PostResolutionInterceptorInterface.');

        $registry->matchPost(new stdClass());
    }

    public function testMatchPreThrowsWhenResolverReturnsInvalidInstance(): void
    {
        $resolver = $this->createMock(ServiceResolverInterface::class);
        $resolver->method('resolve')->willReturn(new stdClass());

        $registry = new InterceptorRegistry();
        $registry->setResolver($resolver);
        $registry->registerPre(StubPreInterceptor::class);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Resolved interceptor must implement PreResolutionInterceptorInterface.');

        $registry->matchPre('StubMatch');
    }
}
