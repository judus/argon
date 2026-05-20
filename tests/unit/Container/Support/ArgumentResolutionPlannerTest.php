<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Support;

use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use Maduser\Argon\Container\Support\ArgumentResolutionPlanner;
use Maduser\Argon\Container\Support\ArgumentResolutionStep;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use Tests\Unit\Container\Mocks\Logger;

final class ArgumentResolutionPlannerTest extends TestCase
{
    public function testUnionPlanSkipsBuiltinTypesAndFallsBackToServiceWhenContextualBindingDisappears(): void
    {
        $bindings = $this->createMock(ContextualBindingsInterface::class);
        $calls = 0;
        $bindings->method('has')->willReturnCallback(
            static function (string $context, string $dependency) use (&$calls): bool {
                self::assertSame('Context', $context);
                self::assertSame(Logger::class, $dependency);

                return $calls++ === 0;
            }
        );

        $parameter = (new ReflectionFunction(
            static function (Logger|string $_dependency): void {
            }
        ))->getParameters()[0];

        $plan = (new ArgumentResolutionPlanner($bindings))->build(
            $parameter,
            'Context',
            'Service',
            []
        );

        $steps = $plan->steps();
        $this->assertSame(ArgumentResolutionStep::RUNTIME_ARGUMENT, $steps[0]->kind());
        $this->assertSame(ArgumentResolutionStep::SERVICE, $steps[1]->kind());
        $this->assertSame(Logger::class, $steps[1]->serviceId());
    }
}
