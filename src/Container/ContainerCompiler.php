<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use ReflectionClass;
use ReflectionException;

class ContainerCompiler
{
    private ServiceContainer $container;

    public function __construct(ServiceContainer $container)
    {
        $this->container = $container;
    }

    public function compileToFile(string $outputPath, string $className = 'CachedContainer'): void
    {
        $serviceDefinitions = $this->getCompiledServiceDefinitions();
        $tagMappings = $this->getTagMappings();

        $matchEntries = [];
        $methodBodies = [];

        foreach ($serviceDefinitions as $id => [$fqcn, $dependencies]) {
            $methodName = $this->methodNameFromClass($id);
            $matchEntries[] = "            '" . addslashes($id) . "' => \$this->$methodName(),";

            $dependencyCalls = array_map(fn($dep) => "\$this->get('$dep')", $dependencies);
            $args = implode(', ', $dependencyCalls);
            $methodBodies[] = "    private function $methodName(): \\{$fqcn}\n    " .
                "{\n        return new \\{$fqcn}($args);\n    }"
            ;
        }

        $matchBlock = implode("\n", $matchEntries);
        $methods = implode("\n\n", $methodBodies);
        $tagsExport = var_export($tagMappings, true);

        $classCode = <<<PHP
<?php

declare(strict_types=1);

use Maduser\Argon\Container\ServiceContainer;
use Maduser\Argon\Container\Exceptions\NotFoundException;

class $className extends ServiceContainer
{  
    public function __construct()
    {
        parent::__construct();
        \$this->tags = $tagsExport;
    }
    
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

    /**
     * Extracts tag mappings from the container.
     *
     * @return array<string, string[]> [tag => [serviceIds]]
     */
    private function getTagMappings(): array
    {
        if (!property_exists($this->container, 'tags')) {
            return [];
        }

        $reflection = new \ReflectionObject($this->container);

        try {
            $prop = $reflection->getProperty('tags');
            $prop->setAccessible(true);
            return $prop->getValue($this->container) ?? [];
        } catch (\ReflectionException) {
            return [];
        }
    }
}
