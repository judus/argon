<?php

declare(strict_types=1);

namespace Tests\Integration\Interceptors;

use InvalidArgumentException;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\Interceptors\ValidationInterceptor;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Tests\Integration\Interceptors\Mocks\InvalidRequest;
use Tests\Integration\Interceptors\Mocks\ValidRequest;

class ValidationInterceptorTest extends TestCase
{
    /**
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function testValidationInterceptorCallsValidateMethod(): void
    {
        // Arrange
        $container = new ServiceContainer();
        $container->registerTypeInterceptor(ValidationInterceptor::class);

        // Provide a valid request object
        $container->bind(ValidRequest::class);

        // Act
        $request = $container->get(ValidRequest::class);

        // Assert
        $this->assertTrue($request->wasValidated);
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testValidationInterceptorThrowsOnInvalidRequest(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Title is required.');

        $container = new ServiceContainer();
        $container->bind(InvalidRequest::class);

        $container->registerTypeInterceptor(ValidationInterceptor::class);

        $container->get(InvalidRequest::class);
    }
}
