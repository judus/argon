<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Support;

use Maduser\Argon\Container\Support\ArgumentResolutionPlan;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Unit\Container\Mocks\Logger;

final class ArgumentResolutionPlanTest extends TestCase
{
    public function testCanResolveClassStringReturnsTrueForMatchingClassString(): void
    {
        $plan = new ArgumentResolutionPlan(
            'logger',
            'Context',
            'Service',
            Logger::class,
            Logger::class,
            false,
            []
        );

        $this->assertTrue($plan->canResolveClassString(Logger::class));
    }

    public function testResolveClassStringReturnsNullForNonMatchingClassString(): void
    {
        $plan = new ArgumentResolutionPlan(
            'logger',
            'Context',
            'Service',
            Logger::class,
            Logger::class,
            false,
            []
        );

        $this->assertNull($plan->resolveClassString(stdClass::class));
    }
}
