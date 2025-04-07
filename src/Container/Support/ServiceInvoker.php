<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Support;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;

/**
 * Callable that directly invokes a compiled service method,
 * or falls back to dynamic invocation if not compiled.
 *
 * @api
 */
class ServiceInvoker
{
    /**
     * @param ArgonContainer $container
     * @param class-string $serviceId
     * @param string $method
     */
    public function __construct(
        private ArgonContainer $container,
        private string $serviceId,
        private string $method = '__invoke',
    ) {
    }

    /**
     * @param array<string, mixed> $arguments
     * @return mixed
     * @throws ContainerException|NotFoundException
     */
    public function __invoke(array $arguments = []): mixed
    {
        $compiledMethod = StringHelper::invokeServiceMethod($this->serviceId, $this->method);

        if (method_exists($this->container, $compiledMethod)) {
            return $this->container->{$compiledMethod}($arguments);
        }

        return $this->container->invoke([$this->serviceId, $this->method], $arguments);
    }
}
