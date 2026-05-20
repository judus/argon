<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Compiler;

use Closure;
use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Contracts\ServiceDescriptorInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use ReflectionClass;
use ReflectionException;

final class ContainerDescriptorValidator
{
    /**
     * @throws ContainerException
     * @throws ReflectionException
     */
    public function validate(ArgonContainer $container): void
    {
        foreach ($container->getBindings() as $id => $descriptor) {
            if ($descriptor->shouldCompile() === false) {
                continue;
            }

            $this->validateDescriptor($id, $descriptor);
        }
    }

    /**
     * @throws ContainerException
     * @throws ReflectionException
     */
    private function validateDescriptor(string $id, ServiceDescriptorInterface $descriptor): void
    {
        $concrete = $descriptor->getConcrete();

        if ($concrete instanceof Closure) {
            throw new ContainerException(
                "Cannot compile a container with closures: [$id]. " .
                "Use skipCompilation() to exclude from compilation."
            );
        }

        if ($descriptor->hasFactory()) {
            $this->validateFactory($id, $descriptor);
            return;
        }

        $reflection = new ReflectionClass($concrete);
        if (!$reflection->isInstantiable()) {
            $target = " [$concrete]";
            throw new ContainerException(
                "Service [$id] points to non-instantiable class$target."
            );
        }
    }

    /**
     * @throws ContainerException
     * @throws ReflectionException
     */
    private function validateFactory(string $id, ServiceDescriptorInterface $descriptor): void
    {
        $factoryClass = $descriptor->getFactoryClass();
        $factoryMethod = $descriptor->getFactoryMethod();

        if ($factoryClass === null) {
            throw ContainerException::fromServiceId($id, 'Factory class not defined.');
        }

        $reflection = new ReflectionClass($factoryClass);

        if (!$reflection->hasMethod($factoryMethod)) {
            throw new ContainerException(sprintf(
                'Factory method "%s" not found on class "%s".',
                $factoryMethod,
                $factoryClass
            ));
        }

        $method = $reflection->getMethod($factoryMethod);
        if (!$method->isPublic()) {
            throw ContainerException::fromServiceId(
                $id,
                sprintf('Factory method "%s" on class "%s" is not public.', $factoryMethod, $factoryClass)
            );
        }
    }
}
