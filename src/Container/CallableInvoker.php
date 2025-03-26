<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\CallableWrapperInterface;
use Maduser\Argon\Container\Contracts\ParameterResolverInterface;
use Maduser\Argon\Container\Contracts\ServiceResolverInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\Support\CallableWrapper;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

readonly class CallableInvoker
{
    public function __construct(
        private ServiceResolverInterface $serviceResolver,
        private ParameterResolverInterface $parameterResolver
    ) {
    }

    /**
     * Resolves and calls a method or closure, injecting dependencies.
     *
     * @param object|string $target
     * @param string|null $method
     * @param array<string, mixed> $parameters
     * @return mixed
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function call(object|string $target, ?string $method = null, array $parameters = []): mixed
    {
        $callableWrapper = $this->resolveCallable($target, $method);

        $resolvedParams = array_map(
            fn(ReflectionParameter $param): mixed => $this->parameterResolver->resolve($param, $parameters),
            $callableWrapper->getReflection()->getParameters()
        );

        return $this->invokeCallable($callableWrapper, $resolvedParams);
    }

    /**
     * Resolves a callable target and provides the correct reflection.
     *
     * @param object|string $target
     * @param string|null $method
     * @return CallableWrapperInterface
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function resolveCallable(object|string $target, ?string $method): CallableWrapperInterface
    {
        try {
            if ($target instanceof Closure) {
                return new CallableWrapper(null, new ReflectionFunction($target));
            }

            if (is_string($target)) {
                $target = $this->serviceResolver->resolve($target);
            }

            if ($method !== null) {
                return new CallableWrapper($target, new ReflectionMethod($target, $method));
            }
        } catch (ReflectionException $e) {
            throw new ContainerException(
                sprintf(
                    'Failed to reflect callable: %s::%s',
                    is_object($target) ? $target::class : $target,
                    $method ?? 'closure'
                ),
                $target instanceof \Stringable ? (string) $target : null,
                0,
                $e
            );
        }

        throw new ContainerException(sprintf(
            'Unsupported callable type: %s (method: %s)',
            get_debug_type($target),
            (string) $method
        ));
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
        } catch (\Throwable $e) {
            throw ContainerException::forInstantiationFailure($reflection->getName(), $e);
        }
    }
}
