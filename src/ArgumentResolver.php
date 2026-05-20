<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\ArgumentMapInterface;
use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use Maduser\Argon\Container\Contracts\ContextualResolverInterface;
use Maduser\Argon\Container\Contracts\ArgumentResolverInterface;
use Maduser\Argon\Container\Contracts\ServiceResolverInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\Support\ArgumentResolutionPlan;
use Maduser\Argon\Container\Support\ArgumentResolutionPlanner;
use Maduser\Argon\Container\Support\ArgumentResolutionStep;
use Maduser\Argon\Container\Support\DebugTrace;
use Override;
use ReflectionParameter;

/**
 * Resolves constructor and method parameters with contextual or container-based resolution.
 */
final class ArgumentResolver implements ArgumentResolverInterface
{
    private ?ServiceResolverInterface $serviceResolver = null;

    private readonly ArgumentResolutionPlanner $planner;

    public function __construct(
        private readonly ContextualResolverInterface $contextualResolver,
        private readonly ArgumentMapInterface $arguments,
        private readonly ContextualBindingsInterface $contextualBindings
    ) {
        $this->planner = new ArgumentResolutionPlanner($contextualBindings);
    }

    #[Override]
    public function setServiceResolver(ServiceResolverInterface $resolver): void
    {
        $this->serviceResolver = $resolver;
    }

    /**
     * @param ReflectionParameter $param
     * @param array<array-key, mixed> $overrides
     * @param string|null $contextId
     * @return mixed
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    #[Override]
    public function resolve(
        ReflectionParameter $param,
        array $overrides = [],
        ?string $contextId = null
    ): mixed {
        $context = $contextId ?? $param->getDeclaringClass()?->getName() ?? 'global';
        $boundArguments = $this->arguments->get($context);
        $plan = $this->planner->build(
            $param,
            $context,
            $context,
            $boundArguments
        );

        return $this->executePlan($plan, $overrides);
    }

    /**
     * @param array<array-key, mixed> $overrides
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function executePlan(ArgumentResolutionPlan $plan, array $overrides): mixed
    {
        foreach ($plan->steps() as $step) {
            if (
                $step->kind() === ArgumentResolutionStep::RUNTIME_ARGUMENT
                && !array_key_exists($plan->parameterName(), $overrides)
            ) {
                continue;
            }

            return $this->executeStep($plan, $step, $overrides);
        }

        throw ContainerException::fromInternalError('Argument resolution plan did not produce a value.');
    }

    /**
     * @param array<array-key, mixed> $overrides
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function executeStep(
        ArgumentResolutionPlan $plan,
        ArgumentResolutionStep $step,
        array $overrides
    ): mixed {
        $paramName = $plan->parameterName();

        switch ($step->kind()) {
            case ArgumentResolutionStep::RUNTIME_ARGUMENT:
                return $this->resolveValue($plan, $overrides[$paramName]);

            case ArgumentResolutionStep::BOUND_ARGUMENT:
            case ArgumentResolutionStep::DEFAULT_VALUE:
                return $this->resolveValue($plan, $step->value());

            case ArgumentResolutionStep::CONTEXTUAL_SERVICE:
                $dependency = $step->dependency();
                if ($dependency === null) {
                    throw ContainerException::fromInternalError('Contextual resolution step misses dependency.');
                }

                return $this->resolveTypeName(
                    $dependency,
                    $plan->context(),
                    $paramName,
                    $plan->expectedType()
                );

            case ArgumentResolutionStep::SERVICE:
                $serviceId = $step->serviceId();
                if ($serviceId === null) {
                    throw ContainerException::fromInternalError('Service resolution step misses service id.');
                }

                return $this->resolveTypeName(
                    $serviceId,
                    $plan->context(),
                    $paramName,
                    $plan->expectedType()
                );

            case ArgumentResolutionStep::NULL_VALUE:
                DebugTrace::add($plan->context(), $paramName, $plan->expectedType(), null);
                return null;

            case ArgumentResolutionStep::PRIMITIVE_FAILURE:
                DebugTrace::fail($plan->context(), $paramName, $plan->expectedType());
                throw ContainerException::forUnresolvedPrimitive($plan->context(), $paramName);

            case ArgumentResolutionStep::FAILURE:
                DebugTrace::fail($plan->context(), $paramName, $plan->expectedType());
                throw ContainerException::fromServiceId($plan->serviceId(), (string) $step->message());
        }

        throw ContainerException::fromInternalError("Unknown argument resolution step '{$step->kind()}'.");
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function resolveValue(ArgumentResolutionPlan $plan, mixed $value): mixed
    {
        $classString = $plan->resolveClassString($value);
        if ($classString !== null) {
            return $this->resolveTypeName(
                $classString,
                $plan->context(),
                $plan->parameterName(),
                $plan->expectedType()
            );
        }

        DebugTrace::add(
            $plan->context(),
            $plan->parameterName(),
            $plan->expectedType(),
            is_scalar($value) ? (string) $value : gettype($value)
        );

        return $value;
    }

    /**
     * @return object
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function resolveTypeName(
        string $serviceId,
        string $className,
        ?string $paramName = null,
        ?string $expectedType = null
    ): object {
        $snapshot = DebugTrace::snapshot();
        if ($this->contextualBindings->has($className, $serviceId)) {
            $instance = $this->contextualResolver->resolve($className, $serviceId);
        } else {
            if ($this->serviceResolver === null) {
                throw ContainerException::fromServiceId($serviceId, 'ParameterResolver: missing ServiceResolver.');
            }

            $instance = $this->serviceResolver->resolve($serviceId);
        }
        $nestedTrace = DebugTrace::diff($snapshot);

        if ($paramName !== null) {
            $type = $expectedType ?? $serviceId;
            DebugTrace::add($className, $paramName, $type, $instance);

            if ($nestedTrace !== []) {
                DebugTrace::nest($className, $paramName, $nestedTrace);
            }
        }

        return $instance;
    }
}
