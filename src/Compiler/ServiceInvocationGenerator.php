<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Compiler;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\ServiceDescriptor;
use Maduser\Argon\Container\Support\StringHelper;
use Nette\PhpGenerator\ClassType;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;

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

                $casts = $this->buildPrimitiveCastLines($descriptor, $method);
                $indent = str_repeat(' ', 20);

                $lines = [
                    $indent . $controllerFetch,
                    $indent . '$mergedArgs = ' . 'array_merge([' . implode(", ", $compiledArgs) . '], $args);',
                ];

                foreach ($casts as $castLine) {
                    $lines[] = $indent . $castLine;
                }

                $lines[] = $indent . "return \$controller->{$method}(...\$mergedArgs);";

                $body = implode("\n", $lines);

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
        $sanitizedService = StringHelper::sanitizeIdentifier($serviceId);
        $sanitizedMethod  = StringHelper::sanitizeIdentifier($method);

        return 'invoke_' . $sanitizedService . '__' . $sanitizedMethod;
    }

    /**
     * @return list<string>
     */
    private function buildPrimitiveCastLines(ServiceDescriptor $descriptor, string $method): array
    {
        $concrete = $descriptor->getConcrete();

        if (!is_string($concrete) || !class_exists($concrete)) {
            return [];
        }

        try {
            $reflection = new ReflectionMethod($concrete, $method);
        } catch (ReflectionException) {
            return [];
        }

        $casts = [];

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
                continue;
            }

            $name = $parameter->getName();
            $condition = "array_key_exists('{$name}', \$mergedArgs) && \$mergedArgs['{$name}'] !== null";

            switch ($type->getName()) {
                case 'int':
                    $casts[] = "if ({$condition}) { \$mergedArgs['{$name}'] = (int) \$mergedArgs['{$name}']; }";
                    break;
                case 'float':
                case 'double':
                    $casts[] = "if ({$condition}) { \$mergedArgs['{$name}'] = (float) \$mergedArgs['{$name}']; }";
                    break;
                default:
                    break;
            }
        }

        return $casts;
    }
}
