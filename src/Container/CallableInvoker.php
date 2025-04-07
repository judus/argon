<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\CallableWrapperInterface;
use Maduser\Argon\Container\Contracts\ArgumentResolverInterface;
use Maduser\Argon\Container\Contracts\ServiceResolverInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\Support\CallableWrapper;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use Stringable;
use Throwable;

readonly class CallableInvoker
{
    public function __construct(
        private ServiceResolverInterface $serviceResolver,
        private ArgumentResolverInterface $argumentResolver
    ) {
    }

    /**
     * Resolves and calls a method or closure, injecting dependencies.
     *
     * @param object|string|array|callable $target
     * @param array<array-key, mixed> $arguments
     * @return mixed
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function call(object|string|array|callable $target, array $arguments = []): mixed
    {
        $callable = $this->resolveCallable($target);

        $contextId = $this->buildContextId($callable);

        $resolvedParams = array_map(
            fn(ReflectionParameter $param): mixed =>
            $this->argumentResolver->resolve($param, $arguments, $contextId),
            $callable->getReflection()->getParameters()
        );

        return $this->invokeCallable($callable, $resolvedParams);
    }

    private function buildContextId(CallableWrapperInterface $callable): string
    {
        $ref = $callable->getReflection();

        if ($ref instanceof ReflectionFunction) {
            return 'closure';
        }

        $instance = $callable->getInstance();
        $class = is_object($instance) ? get_class($instance) : 'unknown';

        return "$class::{$ref->getName()}";
    }

    /**
     * Resolves a callable target and provides the correct reflection.
     *
     * @param object|string|array|callable $target
     * @return CallableWrapperInterface
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function resolveCallable(object|string|array|callable $target): CallableWrapperInterface
    {
        try {
            // 1. Direct Closure
            if ($target instanceof Closure) {
                return new CallableWrapper(null, new ReflectionFunction($target));
            }

            // 2. Array callable: [$class, 'method'] or [$instance, 'method']
            if (is_array($target) && count($target) === 2) {
                [$handler, $method] = $target;

                if (is_string($handler)) {
                    $handler = $this->serviceResolver->resolve($handler);
                }

                if (is_object($handler)) {
                    return new CallableWrapper($handler, new ReflectionMethod($handler, (string) $method));
                }
            }

            // 3. Callable object (with __invoke)
            if (is_object($target) && method_exists($target, '__invoke')) {
                return new CallableWrapper($target, new ReflectionMethod($target, '__invoke'));
            }

            // 4. String class → resolve and use __invoke
            if (is_string($target) && class_exists($target)) {
                $resolved = $this->serviceResolver->resolve($target);
                return new CallableWrapper($resolved, new ReflectionMethod($resolved, '__invoke'));
            }

            // 5. String "Class::method"
            if (is_string($target) && str_contains($target, '::')) {
                $parts = explode('::', $target, 2);
                if (count($parts) === 2) {
                    [$class, $method] = $parts;
                    $resolved = $this->serviceResolver->resolve($class);
                    return new CallableWrapper($resolved, new ReflectionMethod($resolved, $method));
                }
            }

            throw new ContainerException("Cannot resolve callable.");
        } catch (ReflectionException $e) {
            throw new ContainerException("Failed to reflect callable: " . get_debug_type($target), 0, $e);
        }
    }


    /**
     * Invokes the given callable using reflection and resolved parameters.
     *
     * @param CallableWrapperInterface $callable
     * @param array<int, mixed> $resolvedParams
     * @return mixed
     * @throws ContainerException
     */
    private function invokeCallable(CallableWrapperInterface $callable, array $resolvedParams): mixed
    {
        $reflection = $callable->getReflection();

        try {
            return match (true) {
                $reflection instanceof ReflectionMethod => $reflection
                    ->invokeArgs($callable->getInstance(), $resolvedParams),
                $reflection instanceof ReflectionFunction => $reflection->invokeArgs($resolvedParams),
                default => throw new ContainerException('Unhandled reflection type: ' . get_class($reflection)),
            };
        } catch (Throwable $e) {
            throw ContainerException::forInstantiationFailure($reflection->getName(), $e);
        }
    }
}
