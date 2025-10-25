<?php

declare(strict_types=1);

namespace Tests\Integration\Compiler\Mocks;

final class ServiceWithDependency
{
    public function doSomething(Logger $logger): string
    {
        return $logger->log('from-invoker');
    }
}
