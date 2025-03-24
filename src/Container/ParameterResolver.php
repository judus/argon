<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

class ParameterResolver
{
    private ?ServiceResolver $serviceResolver = null;

    public function __construct(
        private readonly ContextualResolver $contextualResolver,
        private readonly ParameterRegistry $registry,
        private readonly ContextualBindings $contextualBindings
    ) {
    }

    public function setServiceResolver(ServiceResolver $resolver): void
    {
        $this->serviceResolver = $resolver;
    }

    /**
     * Resolves a parameter for a given consumer (class/method).
     *
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function resolve(ReflectionParameter $param, array $overrides = []): mixed
    {
        $className = $param->getDeclaringClass()?->getName() ?? 'unknown class';
        $paramName = $param->getName();

        $merged = array_merge($this->registry->get($className), $overrides);

        if (array_key_exists($paramName, $merged)) {
            return $merged[$paramName];
        }

        /** @var ReflectionNamedType|null $type */
        $type = $param->getType();

        if ($type !== null && !$type->isBuiltin()) {
            $typeName = $type->getName();

            if ($this->contextualBindings->has($className, $typeName)) {
                return $this->contextualResolver->resolve($className, $typeName);
            }

            if (!$this->serviceResolver) {
                throw new \RuntimeException("ParameterResolver: missing ServiceResolver.");
            }

            return $this->serviceResolver->resolve($typeName);
        }

        if ($param->isOptional()) {
            return $param->getDefaultValue();
        }

        throw ContainerException::forUnresolvedPrimitive($className, $paramName);
    }
}
