<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

readonly class CallableInvoker
{
    public function __construct(
        private ServiceResolver $serviceResolver,
        private ParameterResolver $parameterResolver
    ) {
    }

    /**
     * Resolves and calls a method or closure, injecting dependencies.
     *
     * @param object|string $target
     * @param string|null $method
     * @param array $parameters
     * @return mixed
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function call(object|string $target, ?string $method = null, array $parameters = []): mixed
    {
        [$callable, $reflection] = $this->resolveCallable($target, $method);

        $resolvedParams = array_map(
            fn(ReflectionParameter $param): mixed => $this->parameterResolver->resolve($param, $parameters),
            $reflection->getParameters()
        );

        // For class methods
        if ($reflection instanceof ReflectionMethod) {
            return $reflection->invokeArgs($callable, $resolvedParams);
        }

        // For closures
        if ($reflection instanceof ReflectionFunction) {
            return $reflection->invokeArgs($resolvedParams);
        }

        throw new ContainerException('Unhandled reflection type.');
    }

    /**
     * Resolves a callable target and provides the correct reflection.
     *
     * @param object|string $target
     * @param string|null $method
     * @return array{object|null, \ReflectionFunctionAbstract}
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function resolveCallable(object|string $target, ?string $method): array
    {
        if (!is_null($method)) {
            $object = is_string($target)
                ? $this->serviceResolver->resolve($target)
                : $target;

            return [$object, new ReflectionMethod($object, $method)];
        }

        if ($target instanceof Closure) {
            return [null, new ReflectionFunction($target)];
        }

        throw new ContainerException("Unsupported callable type: must be method or closure.");
    }
}
