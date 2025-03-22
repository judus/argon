<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\ServiceContainer;
use Maduser\Argon\Container\ServiceDescriptor;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;

class ContainerCompiler
{
    private ServiceContainer $container;

    public function __construct(ServiceContainer $container)
    {
        $this->container = $container;
    }

    public function compileToFile(string $outputPath): void
    {
        $serviceDefinitions = $this->getCompiledServiceDefinitions();

        $matchEntries = [];
        $methodBodies = [];

        foreach ($serviceDefinitions as $id => [$fqcn, $dependencies]) {
            $methodName = $this->methodNameFromClass($id);
            $matchEntries[] = "            '$id' => \$this->$methodName(),";

            $dependencyCalls = array_map(fn($dep) => "\$this->get('$dep')", $dependencies);
            $args = implode(', ', $dependencyCalls);
            $methodBodies[] = "    private function $methodName(): \\{$fqcn}\n    {\n        return new \\{$fqcn}($args);\n    }";
        }

        $matchBlock = implode("\n", $matchEntries);
        $methods = implode("\n\n", $methodBodies);

        $classCode = <<<PHP
<?php

declare(strict_types=1);

use Maduser\Argon\Container\ServiceContainer;
use Maduser\Argon\Container\Exceptions\NotFoundException;

class CachedContainer extends ServiceContainer
{
    public function get(string \$id): object
    {
        return match (\$id) {
$matchBlock
            default => parent::get(\$id),
        };
    }

$methods
}
PHP;

        file_put_contents($outputPath, $classCode);
    }

    private function methodNameFromClass(string $fqcn): string
    {
        return 'get' . str_replace(['\\', '/'], '', $fqcn);
    }

    /**
     * Extracts concrete class service definitions from the container.
     *
     * @return array<string, array{string, string[]}> [serviceId => [fqcn, [dependencies]]]
     */
    private function getCompiledServiceDefinitions(): array
    {
        $definitions = [];

        foreach ($this->container->getServices() as $id => $descriptor) {
            $concrete = $descriptor->getConcrete();

            if ($concrete instanceof \Closure) {
                continue; // Skip closures
            }

            $fqcn = $concrete;

            try {
                $reflection = new ReflectionClass($fqcn);

                if (!$reflection->isInstantiable()) {
                    continue;
                }

                $dependencies = [];

                if ($constructor = $reflection->getConstructor()) {
                    foreach ($constructor->getParameters() as $param) {
                        $type = $param->getType();
                        if ($type && !$type->isBuiltin()) {
                            $dependencies[] = $type->getName();
                        }
                    }
                }

                $definitions[$id] = [$fqcn, $dependencies];

            } catch (ReflectionException) {
                continue;
            }
        }

        return $definitions;
    }
}
