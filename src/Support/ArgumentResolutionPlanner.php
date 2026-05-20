<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Support;

use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

final readonly class ArgumentResolutionPlanner
{
    public function __construct(private ContextualBindingsInterface $contextualBindings)
    {
    }

    /**
     * @param array<array-key, mixed> $boundArguments
     */
    public function build(
        ReflectionParameter $parameter,
        string $context,
        string $serviceId,
        array $boundArguments
    ): ArgumentResolutionPlan {
        $name = $parameter->getName();
        $type = $parameter->getType();
        $namedType = $type instanceof ReflectionNamedType ? $type : null;
        $namedTypeName = $namedType?->getName();

        $steps = [ArgumentResolutionStep::runtimeArgument()];

        if (array_key_exists($name, $boundArguments)) {
            $steps[] = ArgumentResolutionStep::boundArgument($boundArguments[$name]);

            return $this->createPlan($parameter, $context, $serviceId, $steps);
        }

        if (
            $namedTypeName === 'mixed'
            && !$parameter->isDefaultValueAvailable()
            && !$parameter->isOptional()
        ) {
            $steps[] = ArgumentResolutionStep::failure(sprintf(
                "Cannot resolve parameter \$%s in %s::__construct(): " .
                "parameter is of type 'mixed' with no default or nullability",
                $name,
                $context
            ));

            return $this->createPlan($parameter, $context, $serviceId, $steps);
        }

        if ($namedType !== null && !$namedType->isBuiltin()) {
            $contextual = $this->contextualStep($context, $namedType->getName());

            if ($parameter->allowsNull()) {
                $steps[] = $contextual ?? ArgumentResolutionStep::nullValue();

                return $this->createPlan($parameter, $context, $serviceId, $steps);
            }

            $steps[] = $contextual ?? ArgumentResolutionStep::service($namedType->getName());

            return $this->createPlan($parameter, $context, $serviceId, $steps);
        }

        if ($type instanceof ReflectionUnionType) {
            return $this->buildUnionPlan($parameter, $context, $serviceId, $steps, $type);
        }

        if ($parameter->isDefaultValueAvailable()) {
            $steps[] = ArgumentResolutionStep::defaultValue($parameter->getDefaultValue());

            return $this->createPlan($parameter, $context, $serviceId, $steps);
        }

        if ($parameter->allowsNull()) {
            $steps[] = ArgumentResolutionStep::nullValue();

            return $this->createPlan($parameter, $context, $serviceId, $steps);
        }

        $steps[] = ArgumentResolutionStep::primitiveFailure();

        return $this->createPlan($parameter, $context, $serviceId, $steps);
    }

    /**
     * @param list<ArgumentResolutionStep> $steps
     */
    private function buildUnionPlan(
        ReflectionParameter $parameter,
        string $context,
        string $serviceId,
        array $steps,
        ReflectionUnionType $type
    ): ArgumentResolutionPlan {
        $userDefined = [];

        foreach ($type->getTypes() as $unionType) {
            if (!$unionType instanceof ReflectionNamedType || $unionType->isBuiltin()) {
                continue;
            }

            $typeName = $unionType->getName();
            if ($this->contextualBindings->has($context, $typeName)) {
                $userDefined[] = $typeName;
            }
        }

        if (count($userDefined) === 1) {
            $steps[] = $this->contextualStep($context, $userDefined[0])
                ?? ArgumentResolutionStep::service($userDefined[0]);

            return $this->createPlan($parameter, $context, $serviceId, $steps);
        }

        $steps[] = ArgumentResolutionStep::failure(sprintf(
            'Ambiguous union type for parameter $%s in %s::__construct(): [%s]',
            $parameter->getName(),
            $context,
            $this->formatUnionTypes($type)
        ));

        return $this->createPlan($parameter, $context, $serviceId, $steps);
    }

    private function contextualStep(string $context, string $dependency): ?ArgumentResolutionStep
    {
        if (!$this->contextualBindings->has($context, $dependency)) {
            return null;
        }

        $target = $this->contextualBindings->get($context, $dependency);

        return ArgumentResolutionStep::contextualService(
            $dependency,
            is_string($target) ? $target : null
        );
    }

    private function formatUnionTypes(ReflectionUnionType $type): string
    {
        return implode(', ', array_map(
            static fn(ReflectionNamedType $type): string => $type->getName(),
            array_filter(
                $type->getTypes(),
                static fn(ReflectionType $type): bool => $type instanceof ReflectionNamedType
            )
        ));
    }

    /**
     * @param list<ArgumentResolutionStep> $steps
     */
    private function createPlan(
        ReflectionParameter $parameter,
        string $context,
        string $serviceId,
        array $steps
    ): ArgumentResolutionPlan {
        $type = $parameter->getType();
        $namedType = $type instanceof ReflectionNamedType ? $type : null;

        return new ArgumentResolutionPlan(
            $parameter->getName(),
            $context,
            $serviceId,
            $namedType?->getName() ?? 'mixed',
            $namedType?->getName(),
            $namedType?->isBuiltin() ?? false,
            $steps
        );
    }
}
