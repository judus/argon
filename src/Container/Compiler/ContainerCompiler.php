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


            $args = $this->resolveConstructorArguments($concrete);
            if ($args === null) {
                continue;
            }

            $argString = implode(",\n", $args) . "\n\t\t";

            $class->addMethod($methodName)
                ->setPrivate()
                ->setReturnType('object')
                ->setBody(<<<PHP
                    if (\$this->{$singletonProperty} === null) {
                        \$this->{$singletonProperty} = \$this->applyPostInterceptors(
                            new {$fqcn}({$argString})
                        );
                    }
                    return \$this->{$singletonProperty};
                PHP);

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
                ? $this->{$this->serviceMap[$id]}()
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

        $class->addMethod('has')
            ->setReturnType('bool')
            ->setBody(<<<'PHP'
                return isset($this->serviceMap[$id]) || parent::has($id);
            PHP)
            ->addParameter('id')->setType('string');

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
    private function resolveConstructorArguments(string $class): ?array
    {
        $reflectionClass = new ReflectionClass($class);
        $constructor = $reflectionClass->getConstructor();

        if (!$constructor) {
            return [];
        }

        $resolved = [];

        foreach ($constructor->getParameters() as $param) {
            $value = $this->resolveParameter($param);
            if ($value === null) {
                return null;
            }
            $resolved[] = $value;
        }

        return $resolved;
    }

    private function resolveParameter(ReflectionParameter $parameter): ?string
    {
        $name = $parameter->getName();
        /** @var ReflectionNamedType $type */
        $type = $parameter->getType();
        $typeName = $type->getName();

        $declaringClass = $parameter->getDeclaringClass();

        if ($declaringClass !== null) {
            $className = $declaringClass->getName();

            if ($this->contextualBindings->has($className, $typeName)) {
                $target = $this->contextualBindings->get($className, $typeName);
                if (is_string($target)) {
                    return "\$this->get('{$target}')";
                }
            }

            if ($this->argumentMap->has($className, $name)) {
                return var_export($this->argumentMap->getArgument($className, $name), true);
            }

            if ($parameter->isDefaultValueAvailable()) {
                return var_export($parameter->getDefaultValue(), true);
            }

            if ($typeName && class_exists($typeName)) {
                return "\n\t\t\t\$this->get('{$typeName}')";
            }
        }

        return null;
    }
}
