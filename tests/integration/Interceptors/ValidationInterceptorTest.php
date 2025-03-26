<?php

declare(strict_types=1);

namespace Tests\Integration\Interceptors;

use InvalidArgumentException;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\Interceptors\Post\ValidationInterceptor;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Interceptors\Mocks\BlogPostRequest;
use Tests\Integration\Interceptors\Mocks\InvalidRequest;
use Tests\Integration\Interceptors\Mocks\Request;
use Tests\Integration\Interceptors\Mocks\ValidRequest;

class ValidationInterceptorTest extends TestCase
{
    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testValidationInterceptorCallsValidateMethod(): void
    {
        // Arrange
        $container = new ServiceContainer();
        $container->registerInterceptor(ValidationInterceptor::class);

        // Provide a valid request object
        $container->bind(ValidRequest::class);

        // Act
        $request = $container->get(ValidRequest::class);

        // Assert
        $this->assertTrue($request->wasValidated);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testValidationInterceptorThrowsOnInvalidRequest(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Title is required.');

        $container = new ServiceContainer();
        $container->bind(InvalidRequest::class);

        $container->registerInterceptor(ValidationInterceptor::class);

        $container->get(InvalidRequest::class);
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testFormRequestValidatesAutomatically(): void
    {
        $container = new ServiceContainer();

        // Simulate user input
        $container->singleton(Request::class, fn() => new Request([
            'title' => 'Valid Title',
        ]));

        $container->registerInterceptor(ValidationInterceptor::class);

        // Resolve the FormRequest
        $request = $container->get(BlogPostRequest::class);

        $this->assertTrue($request->wasValidated);
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testFormRequestThrowsIfInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The title is required.');

        $container = new ServiceContainer();

        $container->registerInterceptor(ValidationInterceptor::class);

        // This will throw from validate()
        $container->get(BlogPostRequest::class);
    }
}
