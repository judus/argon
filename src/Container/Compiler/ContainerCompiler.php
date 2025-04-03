<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Compiler;

use Closure;
use Exception;
use Maduser\Argon\Container\Contracts\ArgumentMapInterface;
use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\ServiceDescriptor;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
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
     * @throws ContainerException
     * @throws \ReflectionException
     */
    public function compile(string $filePath, string $className, string $namespace = 'App\\Compiled'): void
    {
        $file = $this->generatePhpFile($namespace);

        $namespaceGen = $file->addNamespace($namespace);
        $namespaceGen->addUse(ArgonContainer::class);
        $namespaceGen->addUse(ContainerException::class);

        $class = $namespaceGen->addClass($className);
        $class->setExtends(ArgonContainer::class);

        $this->generateConstructor($class);
        $this->generateCoreProperties($class);
        $this->generateInterceptorMethods($class);
        $this->generateGetMethod($class);
        $this->generateGetTaggedMethod($class);
        $this->generateGetTaggedIdsMethod($class);
        $this->generateHasMethod($class);
        $this->generateInvokeMethod($class);

        $serviceMap = [];

        foreach ($this->container->getBindings() as $id => $descriptor) {
            $concrete = $descriptor->getConcrete();

            if ($descriptor->shouldIgnoreForCompilation()) {
                continue;
            }

            $methodName = $this->buildServiceMethodName($id);


            if ($descriptor->hasFactory()) {
                $this->compileFactoryService($namespaceGen, $class, $id, $methodName, $descriptor, $serviceMap);
                continue;
            }

            if (!(new ReflectionClass($concrete))->isInstantiable()) {
                $target = is_string($concrete) ? " [$concrete]" : '';
                throw new ContainerException(
                    "Service [$id] points to non-instantiable class$target."
                );
            }

            $methodName = $this->buildServiceMethodName($id);
            $singletonProperty = "singleton_$methodName";

            $this->generateSingletonProperty($class, $singletonProperty, $id);
            $this->generateServiceMethod($class, $concrete, $id, $methodName, $singletonProperty);

            $serviceMap[$id] = $methodName;
        }

        $class->addProperty('serviceMap')->setValue($serviceMap);

        $compiled = (string) $file;

        if (!file_exists($filePath) || md5_file($filePath) !== md5($compiled)) {
            file_put_contents($filePath, $compiled);
        }
    }

    /**
     * @throws ContainerException
     */
    private function compileFactoryService(
        PhpNamespace $namespace,
        ClassType $class,
        string $id,
        string $methodName,
        ServiceDescriptor $descriptor,
        array &$serviceMap
    ): void {
        $factoryClass = $descriptor->getFactoryClass();
        $factoryMethod = $descriptor->getFactoryMethod();

        if (!$factoryClass) {
            throw new ContainerException("Factory class missing for [$id]");
        }

        $fqFactory = '\\' . ltrim($factoryClass, '\\');
        $returnType = class_exists($id) ? '\\' . ltrim($id, '\\') : 'object';
        $singletonProperty = "singleton_$methodName";

        // Add the factory use statement
        $namespace->addUse($factoryClass);

        // Add the singleton property
        $class->addProperty($singletonProperty)
            ->setPrivate()
            ->setType('?' . $returnType)
            ->setValue(null);

        // Add the service method
        $method = $class->addMethod($methodName);
        $method->setPrivate()
            ->setReturnType('object');

        $method->addParameter('args')
            ->setType('array')
            ->setDefaultValue([])
            ->setReference();

        $method->setBody(<<<PHP
            if (\$this->{$singletonProperty} === null) {
                \$this->{$singletonProperty} = (new {$fqFactory}(\$this))->{$factoryMethod}();
            }
            return \$this->{$singletonProperty};
        PHP);

        $serviceMap[$id] = $methodName;
    }

    private function generatePhpFile(string $namespace): PhpFile
    {
        $file = new PhpFile();
        $file->setStrictTypes();
        $namespaceGen = $file->addNamespace($namespace);
        $namespaceGen->addUse(ArgonContainer::class);
        return $file;
    }

    private function generateConstructor(ClassType $class): void
    {
        $constructor = $class->addMethod('__construct')->setPublic();
        $constructor->addBody('parent::__construct();');

        $parameterStore = $this->container->getParameters()->all();
        if (!empty($parameterStore)) {
            $formatted = var_export($parameterStore, true);
            $constructor->addBody("\$this->getParameters()->setStore({$formatted});");
        }
    }

    private function generateCoreProperties(ClassType $class): void
    {
        $class->addProperty('tagMap')->setPrivate()->setValue($this->container->getTags());
        $class->addProperty('parameters')->setPrivate()->setValue($this->container->getParameters()->all());

        $class->addProperty('preInterceptors')->setPrivate()->setValue(array_map(
            fn($i) => '\\' . ltrim($i, '\\'),
            $this->container->getPreInterceptors()
        ));

        $class->addProperty('postInterceptors')->setPrivate()->setValue(array_map(
            fn($i) => '\\' . ltrim($i, '\\'),
            $this->container->getPostInterceptors()
        ));
    }

    private function generateInterceptorMethods(ClassType $class): void
    {
        // Apply Pre-Interceptors
        $pre = $class->addMethod('applyPreInterceptors');
        $pre->setPrivate()
            ->setReturnType('object')
            ->setReturnNullable(true);

        $pre->addParameter('id')->setType('string');
        $pre->addParameter('args')->setType('array')->setDefaultValue([])->setReference();

        $pre->setBody(<<<'PHP'
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

        // Apply Post-Interceptors
        $post = $class->addMethod('applyPostInterceptors');
        $post->setPrivate()
            ->setReturnType('object');

        $post->addParameter('instance')->setType('object');

        $post->setBody(<<<'PHP'
            foreach ($this->postInterceptors as $interceptor) {
                if ($interceptor::supports($instance)) {
                    (new $interceptor())->intercept($instance);
                }
            }
            return $instance;
        PHP);
    }


    private function generateGetMethod(ClassType $class): void
    {
        $method = $class->addMethod('get')
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

        $method->addParameter('id')->setType('string');
        $method->addParameter('args')->setType('array')->setDefaultValue([]);
    }

    private function generateGetTaggedMethod(ClassType $class): void
    {
        $class->addMethod('getTagged')
            ->setReturnType('array')
            ->setBody(<<<'PHP'
                if (!isset($this->tagMap[$tag])) {
                    return [];
                }
                
                $results = [];
                foreach ($this->tagMap[$tag] as $id) {
                    $results[] = $this->get($id);
                }
                return $results;
            PHP)
            ->addParameter('tag')->setType('string');
    }

    private function generateGetTaggedIdsMethod(ClassType $class): void
    {
        $class->addMethod('getTaggedIds')
            ->setReturnType('array')
            ->setBody('return $this->tagMap[$tag] ?? [];')
            ->addParameter('tag')->setType('string')
        ;
    }

    private function generateHasMethod(ClassType $class): void
    {
        $class->addMethod('has')
            ->setReturnType('bool')
            ->setBody('return isset($this->serviceMap[$id]) || parent::has($id);')
            ->addParameter('id')->setType('string');
    }

    private function generateInvokeMethod(ClassType $class): void
    {
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
    }

    private function generateSingletonProperty(ClassType $class, string $propertyName, string $id): void
    {
        $typeHint = class_exists($id) ? '\\' . ltrim($id, '\\') : 'object';
        $class->addProperty($propertyName)
            ->setPrivate()
            ->setType('?' . $typeHint)
            ->setValue(null);
    }

    /**
     * @param class-string|Closure $concrete
     *
     * @throws ContainerException
     * @throws ReflectionException
     */
    private function generateServiceMethod(
        ClassType $class,
        string|Closure $concrete,
        string $id,
        string $methodName,
        string $singletonProperty
    ): void {
        if ($concrete instanceof \Closure) {
            throw new ContainerException("Cannot compile a container with closures: [$id]");
        }

        $fqcn = '\\' . ltrim($concrete, '\\');
        $args = $this->resolveConstructorArguments($concrete, $id, '$args');
        $argString = implode(",\n", $args);

        $class->addMethod($methodName)
            ->setPrivate()
            ->setReturnType('object')
            ->setBody(<<<PHP
                if (\$this->{$singletonProperty} === null) {
                    \$this->{$singletonProperty} = new {$fqcn}({$argString});
                }
                return \$this->{$singletonProperty};
            PHP)
            ->addParameter('args')->setType('array')->setDefaultValue([]);
    }

    private function buildServiceMethodName(string $id): string
    {
        return 'get_' . preg_replace('/[^A-Za-z0-9_]/', '_', $id);
    }

    /**
     * @param class-string $class
     * @return list<string>
     *
     * @throws ReflectionException
     */
    private function resolveConstructorArguments(string $class, string $serviceId, string $argsVar = '$args'): array
    {
        $reflectionClass = new ReflectionClass($class);
        $constructor = $reflectionClass->getConstructor();

        if (!$constructor) {
            return [];
        }

        $resolved = [];

        foreach ($constructor->getParameters() as $param) {
            $resolved[] = $this->resolveParameter($param, $serviceId, $argsVar);
        }

        return $resolved;
    }

    /**
     * Resolves a constructor parameter for code generation in the compiled container.
     *
     * @param ReflectionParameter $parameter
     * @param string $serviceId
     * @param string $argsVar Variable name (e.g. '$args')
     * @return string
     */
    private function resolveParameter(
        ReflectionParameter $parameter,
        string $serviceId,
        string $argsVar = '$args'
    ): string {
        $name = $parameter->getName();
        $type = $parameter->getType();
        $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

        $runtime = "{$argsVar}[" . var_export($name, true) . "]";

        $fallbacks = [];
        $declaringClass = $parameter->getDeclaringClass();
        $className = $declaringClass?->getName() ?? $serviceId;

        if ($typeName !== null) {
            // Contextual bindings
            if ($this->contextualBindings->has($className, $typeName)) {
                $target = $this->contextualBindings->get($className, $typeName);
                if (is_string($target)) {
                    $fallbacks[] = "\$this->get('{$target}')";
                }
            }

            // Registered service in container
            if ($this->container->has($typeName) && !$type?->allowsNull()) {
                $fallbacks[] = "\$this->get('{$typeName}')";
            }

            // Last-resort: autowiring for instantiable classes
            if (class_exists($typeName)) {
                $fallbacks[] = "\$this->get('{$typeName}')";
            }
        }

        // From argument map
        if ($this->argumentMap->has($serviceId, $name)) {
            $fallbacks[] = var_export($this->argumentMap->getArgument($serviceId, $name), true);
        }

        // Default value
        if ($parameter->isDefaultValueAvailable()) {
            $fallbacks[] = var_export($parameter->getDefaultValue(), true);
        }

        return $runtime . ($fallbacks ? ' ?? ' . implode(' ?? ', $fallbacks) : '');
    }
}
