<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Compiler;

use Exception;
use Maduser\Argon\Container\Contracts\ArgumentMapInterface;
use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\ArgonContainer;
use Nette\PhpGenerator\PhpFile;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

final class ContainerCompiler
{
    private ArgumentMapInterface $argumentMap;
    private ContextualBindingsInterface $contextualBindings;

    public function __construct(
        private readonly ArgonContainer $container
    ) {
        $this->argumentMap = $container->getArgumentMap();
        $this->contextualBindings = $container->getContextualBindings();
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function compile(string $filePath, string $className, string $namespace = 'App\\Compiled'): void
    {
        $file = new PhpFile();
        $file->setStrictTypes();

        $namespaceGen = $file->addNamespace($namespace);
        $namespaceGen->addUse(ArgonContainer::class);
        $class = $namespaceGen->addClass($className);
        $class->setExtends(ArgonContainer::class);

        $constructor = $class->addMethod('__construct')
            ->setPublic();

        $constructor->addBody('parent::__construct();');

        // Only add parameter store if there are parameters to hydrate
        $parameterStore = $this->container->getParameters()->all();

        if (!empty($parameterStore)) {
            $formatted = var_export($parameterStore, true);
            $constructor->addBody("\$this->getParameters()->setStore({$formatted});");
        }

        $serviceMap = [];
        $tagMap = $this->container->getTags();

        foreach ($this->container->getBindings() as $id => $descriptor) {
            $methodName = $this->buildServiceMethodName($id);

            $concrete = $descriptor->getConcrete();

            if ($descriptor->shouldIgnoreForCompilation()) {
                continue;
            }

            if ($concrete instanceof \Closure) {
                throw new ContainerException("Cannot compile a container with closures: [$id]");
            }

            $reflection = new ReflectionClass($concrete);

            if (!$reflection->isInstantiable()) {
                throw new ContainerException("Service [$id] points to non-instantiable class [$concrete]");
            }

            $fqcn = '\\' . ltrim($concrete, '\\');

            $singletonProperty = "singleton_$methodName";

            $typeHint = class_exists($id) ? '\\' . ltrim($id, '\\') : 'object';

            $class->addProperty($singletonProperty)
                ->setPrivate()
                ->setType('?' . $typeHint)
                ->setValue(null);


            if ($descriptor->hasFactory()) {
                $factoryClass = '\\' . ltrim($descriptor->getFactoryClass(), '\\');
                $factoryMethod = $descriptor->getFactoryMethod();

                $class->addMethod($methodName)
                    ->setPrivate()
                    ->setReturnType('object')
                    ->setBody(<<<PHP
            if (\$this->{$singletonProperty} === null) {
                \$this->{$singletonProperty} = \$this->get('{$factoryClass}')->{$factoryMethod}();
            }
            return \$this->{$singletonProperty};
        PHP);

                $serviceMap[$id] = $methodName;
                continue; // skip regular constructor-based instantiation
            }

            $args = $this->resolveConstructorArguments($concrete, $id);

            $argString = '';
            if ($args !== null) {
                $argString = implode(",\n", $args) . "\n\t\t";
            }

            $method = $class->addMethod($methodName)
                ->setPrivate()
                ->setReturnType('object')
                ->setBody(<<<PHP
                    if (\$this->{$singletonProperty} === null) {
                        \$this->{$singletonProperty} = new {$fqcn}({$argString});
                    }
                    return \$this->{$singletonProperty};
                PHP);

            $method->addParameter('args')
                ->setType('array')
                ->setDefaultValue([]);

            $serviceMap[$id] = $methodName;
        }

        // --- Service map ---
        $class->addProperty('serviceMap')
            ->setPrivate()
            ->setType('array')
            ->setValue($serviceMap)
            ->addComment('@var array<string, string> Maps service IDs to method names.');

        // --- Tag map ---
        $class->addProperty('tagMap')
            ->setPrivate()
            ->setType('array')
            ->setValue($tagMap)
            ->addComment('@var array<string, list<string>> Maps tag names to service IDs.');

        // --- Parameters ---
        $class->addProperty('parameters')
            ->setPrivate()
            ->setType('array')
            ->setValue($this->container->getParameters()->all())
            ->addComment('@var array<string, mixed>');

        // Pre-resolution interceptors
        $class->addProperty('preInterceptors')
            ->setPrivate()
            ->setType('array')
            ->setValue(array_map(fn($i) => '\\' . ltrim($i, '\\'), $this->container->getPreInterceptors()))
            ->addComment('@var array<class-string> List of pre-resolution interceptors.');

        $applyPre = $class->addMethod('applyPreInterceptors')
            ->setPrivate()
            ->setReturnNullable(true)
            ->setReturnType('object')
            ->setBody(<<<'PHP'
                foreach ($this->preInterceptors as $interceptor) {
                    if ($interceptor::supports($id)) {
                        $result = (new $interceptor())->intercept($id, $args);
                        if ($result !== null) {
                            return $result;
                        }
                    }
                }
                return null;
            PHP);

        $applyPre->addParameter('id')
            ->setType('string');

        $applyPre->addParameter('args')
            ->setType('array')
            ->setDefaultValue([])
            ->setReference();

        // Post-resolution interceptors (same as before)
        $class->addProperty('postInterceptors')
            ->setPrivate()
            ->setType('array')
            ->setValue(array_map(fn($i) => '\\' . ltrim($i, '\\'), $this->container->getPostInterceptors()))
            ->addComment('@var array<class-string> List of post-resolution interceptors.');

        $applyPost = $class->addMethod('applyPostInterceptors')
            ->setPrivate()
            ->setReturnType('object')
            ->setBody(<<<'PHP'
                foreach ($this->postInterceptors as $interceptor) {
                    if ($interceptor::supports($instance)) {
                        (new $interceptor())->intercept($instance);
                    }
                }
                return $instance;
            PHP);

        $applyPost->addParameter('instance')->setType('object');

        // (Service methods and map get injected here, as before...)

        // `get()` method includes pre-interceptor logic now
        $getMethod = $class->addMethod('get')
            ->setReturnType('object')
            ->setBody(<<<'PHP'
            $instance = $this->applyPreInterceptors($id, $args);
            if ($instance !== null) {
                return $instance;
            }
            
            $instance = isset($this->serviceMap[$id])
                ? $this->{$this->serviceMap[$id]}($args)
                : parent::get($id, $args);
                        
            return $this->applyPostInterceptors($instance);
            PHP);

        $getMethod->addParameter('id')->setType('string');
        $getMethod->addParameter('args')->setType('array')->setDefaultValue([]);

        // --- getTagged() override ---
        $getTaggedMethod = $class->addMethod('getTagged');
        $getTaggedMethod->addParameter('tag')->setType('string');
        $getTaggedMethod->setReturnType('array');
        $getTaggedMethod->addComment('@inheritDoc');
        $getTaggedMethod->setBody(<<<'PHP'
            if (!isset($this->tagMap[$tag])) {
                return [];
            }

            $results = [];

            foreach ($this->tagMap[$tag] as $id) {
                $results[] = $this->get($id);
            }

            return $results;
        PHP);

        // --- getTagged() override ---
        $getTaggedIdsMethod = $class->addMethod('getTaggedIds');
        $getTaggedIdsMethod->addParameter('tag')->setType('string');
        $getTaggedIdsMethod->setReturnType('array');
        $getTaggedIdsMethod->addComment('@inheritDoc');
        $getTaggedIdsMethod->setBody(<<<'PHP'
        return $this->tagMap[$tag] ?? [];

        PHP);

        $class->addMethod('has')
            ->setReturnType('bool')
            ->setBody(<<<'PHP'
                return isset($this->serviceMap[$id]) || parent::has($id);
            PHP)
            ->addParameter('id')->setType('string');


        $invoke = $class->addMethod('invoke')
            ->setPublic()
            ->setReturnType('mixed')
            ->setBody(<<<'PHP'
        if ($target instanceof \Closure) {
            $reflection = new \ReflectionFunction($target);
            $instance = null;
        } else {
            if (is_string($target)) {
                if ($this->has($target)) {
                    $instance = $this->get($target);
                } elseif (class_exists($target)) {
                    $instance = (new \ReflectionClass($target))->newInstance();
                } else {
                    throw new \RuntimeException("Cannot invoke unknown class or binding: {$target}");
                }
            } else {
                $instance = $target;
            }

            $reflection = new \ReflectionMethod($instance, $method ?? '__invoke');
        }

        $params = [];

        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType()?->getName();

            if (array_key_exists($name, $arguments)) {
                $params[] = $arguments[$name];
            } elseif ($type && $this->has($type)) {
                $params[] = $this->get($type);
            } elseif ($param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException("Unable to resolve parameter '{$name}' for '{$reflection->getName()}'");
            }
        }

        return $reflection->invokeArgs($instance, $params);
    PHP);

        $invoke->addParameter('target')->setType('object|string');
        $invoke->addParameter('method')->setType('?string')->setDefaultValue(null);
        $invoke->addParameter('arguments')->setType('array')->setDefaultValue([]);

        // Final output
        $compiled = (string) $file;
        if (!file_exists($filePath) || md5_file($filePath) !== md5($compiled)) {
            file_put_contents($filePath, $compiled);
        }
    }

    private function buildServiceMethodName(string $id): string
    {
        return 'get_' . preg_replace('/[^A-Za-z0-9_]/', '_', $id);
    }

    /**
     * @psalm-param class-string $class
     * @return list<string>|null
     * @throws ReflectionException
     * @throws Exception
     */
    private function resolveConstructorArguments(string $class, string $serviceId, string $argsVar = '$args'): ?array
    {
        $reflectionClass = new ReflectionClass($class);
        $constructor = $reflectionClass->getConstructor();

        if (!$constructor) {
            return [];
        }

        $resolved = [];

        foreach ($constructor->getParameters() as $param) {
            $value = $this->resolveParameter($param, $serviceId, $argsVar);
            $resolved[] = $value ?? 'null';
        }

        return $resolved;
    }

    private function resolveParameter(ReflectionParameter $parameter, string $serviceId, string $argsVar = '$args'): ?string
    {
        $name = $parameter->getName();
        $type = $parameter->getType();
        $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

        $runtime = "array_key_exists(" . var_export($name, true) . ", {$argsVar}) ? {$argsVar}[" . var_export($name, true) . "] : null";

        // ✅ First priority: runtime argument passed to `get($id, $args)`
        $runtimeArg = "{$argsVar}[" . var_export($name, true) . "]";

        $fallbacks = [];

        $declaringClass = $parameter->getDeclaringClass();
        $className = $declaringClass?->getName() ?? $serviceId;

        // Contextual binding
        if ($this->contextualBindings->has($className, $typeName)) {
            $target = $this->contextualBindings->get($className, $typeName);
            $fallbacks[] = "\$this->get('{$target}')";
        }

        // Container binding
        if ($this->container->has($typeName) && !$type->allowsNull()) {
            $fallbacks[] = "\$this->get('{$typeName}')";
        }

        // Argument map
        if ($this->argumentMap->has($serviceId, $name)) {
            $val = var_export($this->argumentMap->getArgument($className, $name), true);
            $fallbacks[] = $val;
        }

        // Default value
        if ($parameter->isDefaultValueAvailable()) {
            $fallbacks[] = var_export($parameter->getDefaultValue(), true);
        }

        // Try class instantiation
        if ($typeName && class_exists($typeName)) {
            $fallbacks[] = "\$this->get('{$typeName}')";
        }

        // Merge fallbacks into a clean null coalescing chain
        if ($fallbacks) {
            return "{$runtimeArg} ?? " . implode(" ?? ", $fallbacks);
        }

        // If nothing matches
        return $runtimeArg;
    }
}
