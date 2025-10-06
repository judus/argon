<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Compiler;

use Maduser\Argon\Container\ArgonContainer;
use Nette\PhpGenerator\ClassType;

final class ServiceInvocationGenerator
{
    public function __construct(private readonly ArgonContainer $container)
    {
    }

    public function generate(ClassType $class): void
    {
        foreach ($this->container->getBindings() as $serviceId => $descriptor) {
            foreach ($descriptor->getInvocationMap() as $method => $args) {
                $compiledMethodName = $this->buildMethodInvokerName($serviceId, $method);
                $controllerFetch = "\$controller = \$this->get(" . var_export($serviceId, true) . ");";

                $compiledArgs = [];
                foreach ($args as $name => $value) {
                    if (is_string($value) && str_starts_with($value, '@')) {
                        $className = substr($value, 1);
                        $compiledArgs[] = var_export($name, true) .
                            " => \$this->get(" . var_export($className, true) . ")";
                    } else {
                        $compiledArgs[] = var_export($name, true) .
                            " => " . var_export($value, true);
                    }
                }

                $mergedArgsLine = 'array_merge([' . implode(", ", $compiledArgs) . '], $args)';
                $body = <<<PHP
                    {$controllerFetch}
                    \$mergedArgs = {$mergedArgsLine};
                    return \$controller->{$method}(...\$mergedArgs);
                PHP;

                $class->addMethod($compiledMethodName)
                    ->setPublic()
                    ->setReturnType('mixed')
                    ->setBody($body)
                    ->addParameter('args')->setType('array')->setDefaultValue([]);
            }
        }
    }

    private function buildMethodInvokerName(string $serviceId, string $method): string
    {
        $sanitizedService = preg_replace('/[^A-Za-z0-9_]/', '_', $serviceId);
        $sanitizedMethod  = preg_replace('/[^A-Za-z0-9_]/', '_', $method);

        return 'invoke_' . $sanitizedService . '__' . $sanitizedMethod;
    }
}
