<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Compiler\Stubs;

final class ServiceWithTypedMethods
{
    public function handle(int $id, float $ratio, ServiceDependency $dependency, ?string $note = null): array
    {
        return [$id, $ratio, $dependency, $note];
    }
}
