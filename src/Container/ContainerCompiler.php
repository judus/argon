<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use ReflectionClass;
use ReflectionException;
use Maduser\Argon\Container\Contracts\InterceptorInterface;

class ContainerCompiler
{
    private ServiceContainer $container;

    public function __construct(ServiceContainer $container)
    {
        $this->container = $container;
    }

    public function compileToFile(
        string $outputPath,
        string $className = 'CachedContainer',
        string $namespace = ''
    ): void {
        $serviceDefinitions = $this->getCompiledServiceDefinitions();
        $tagMappings = $this->getTagMappings();
        $compiledParameters = $this->getCompiledParameters();

        $matchEntries = [];
        $matchKeys = [];
        $methodBodies = [];
        $interceptorBodies = [];
        $usedInterceptorClasses = [];

        // Discover interceptors from container
        $interceptors = $this->getApplicableInterceptors();

        foreach ($serviceDefinitions as $id => [$fqcn, $dependencies, $isSingleton]) {
            $methodName = $this->methodNameFromClass($id);
            $matchEntries[] = "            '" . addslashes($id) . "' => \$this->$methodName(),";
            $matchKeys[] = "        '" . addslashes($id) . "',";

            $args = implode(', ', $dependencies);
            $body = "\$instance = new \\{$fqcn}($args);";

            if (isset($interceptors[$fqcn])) {
                $interceptMethod = $interceptors[$fqcn]['method'];
                $body .= "\n        \$instance = \$this->$interceptMethod(\$instance);";

                $interceptorClass = $interceptors[$fqcn]['interceptor'];
                if (!isset($usedInterceptorClasses[$interceptMethod])) {
                    $interceptorBodies[] = <<<PHP
    private function $interceptMethod(\\$fqcn \$instance): \\$fqcn
    {
        \$interceptor = new \\{$interceptorClass}();
        \$interceptor->intercept(\$instance);
        return \$instance;
    }
PHP;
                    $usedInterceptorClasses[$interceptMethod] = true;
                }
            }

            if ($isSingleton) {
                $escapedId = addslashes($id);
                $methodBodies[] = <<<PHP
    private function $methodName(): \\{$fqcn}
    {
        if (isset(\$this->singletons['$escapedId'])) {
            return \$this->singletons['$escapedId'];
        }
        $body
        \$this->singletons['$escapedId'] = \$instance;
        return \$instance;
    }
PHP;
            } else {
                $methodBodies[] = <<<PHP
    private function $methodName(): \\{$fqcn}
    {
        $body
        return \$instance;
    }
PHP;
            }
        }

        $matchBlock = implode("\n", $matchEntries);
        $matchKeysFormatted = implode("\n", $matchKeys);
        $methods = implode("\n\n", array_merge($methodBodies, $interceptorBodies));
        $tagsExport = var_export($tagMappings, true);
        $paramsExport = var_export($compiledParameters, true);
        $namespaceDecl = $namespace !== '' ? "namespace $namespace;\n" : '';

        $classCode = <<<PHP
<?php

declare(strict_types=1);

$namespaceDecl
use Maduser\Argon\Container\ServiceContainer;
use Maduser\Argon\Container\Exceptions\NotFoundException;

class $className extends ServiceContainer
{
    protected array \$tags = $tagsExport;
    protected array \$singletons = [];
    protected array \$compiledParameters = $paramsExport;

    public function __construct()
    {
        parent::__construct();
        \$this->getParameters()->setParameters(\$this->compiledParameters);
    }

    public function get(string \$id): object
    {
        return match (\$id) {
$matchBlock
            default => parent::get(\$id),
        };
    }
    
    public function has(string \$id): bool
    {
        return in_array(\$id, [
$matchKeysFormatted
        ], true) || parent::has(\$id);
    }
    
    public function getTagged(string \$tag): array
    {
        \$taggedServices = [];
    
        if (isset(\$this->tags[\$tag])) {
            foreach (\$this->tags[\$tag] as \$serviceId) {
                \$taggedServices[] = \$this->get(\$serviceId);
            }
        }

    return \$taggedServices;
}

$methods
}
PHP;

        file_put_contents($outputPath, $classCode);
    }

    private function methodNameFromClass(string $fqcn): string
    {
        return 'get_' . preg_replace('/[^A-Za-z0-9_]/', '_', $fqcn);
    }

    /**
     * Extracts concrete class service definitions from the container.
     *
     * @return array<string, array{string, string[], bool}> [serviceId => [fqcn, [dependencies], isSingleton]]
     */
    private function getCompiledServiceDefinitions(): array
    {
        $definitions = [];
        $parameters = $this->container->getParameters();

        foreach ($this->container->getBindings() as $id => $descriptor) {
            $concrete = $descriptor->getConcrete();

            if ($concrete instanceof \Closure) {
                continue; // Skip closures
            }

            $fqcn = $concrete;
            $isSingleton = $descriptor->isSingleton();

            try {
                $reflection = new ReflectionClass($fqcn);

                if (!$reflection->isInstantiable()) {
                    continue;
                }

                $dependencies = [];

                if ($constructor = $reflection->getConstructor()) {
                    $paramOverrides = $parameters->get($fqcn);

                    foreach ($constructor->getParameters() as $param) {
                        $type = $param->getType();
                        $paramName = $param->getName();

                        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                            $dependencies[] = "\$this->get('{$type->getName()}')";
                        } else {
                            $default = array_key_exists($paramName, $paramOverrides)
                                ? var_export($paramOverrides[$paramName], true)
                                : ($param->isDefaultValueAvailable()
                                    ? var_export($param->getDefaultValue(), true)
                                    : 'null');
                            $dependencies[] = $default;
                        }
                    }
                }

                $definitions[$id] = [$fqcn, $dependencies, $isSingleton];
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
        try {
            return $this->container->getTags();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Extracts all scoped parameters for compiled injection.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getCompiledParameters(): array
    {
        return $this->container->getParameters()->all();
    }

    /**
     * Auto-discovers interceptors by calling supports(FQCN) statically.
     *
     * @return array<string, array{interceptor: class-string<InterceptorInterface>, method: string}>
     */
    private function getApplicableInterceptors(): array
    {
        $resolved = [];

        foreach ($this->container->getBindings() as $id => $descriptor) {
            $concrete = $descriptor->getConcrete();
            if ($concrete instanceof \Closure) {
                continue;
            }

            foreach ($this->container->getInterceptors() as $interceptorClass) {
                if ($this->isValidInterceptor($interceptorClass)) {
                    if ($interceptorClass::supports($concrete)) {
                        $method = 'interceptWith' . str_replace(['\\', '/'], '', $interceptorClass);
                        $resolved[$concrete] = [
                            'interceptor' => $interceptorClass,
                            'method' => $method,
                        ];
                    }
                }
            }
        }

        return $resolved;
    }

    /**
     * @param mixed $interceptorClass
     * @return bool
     */
    public function isValidInterceptor(mixed $interceptorClass): bool
    {
        return
            is_string($interceptorClass) &&
            class_exists($interceptorClass) &&
            method_exists($interceptorClass, 'supports');
    }
}
