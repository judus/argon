<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Compiler;

use Closure;
use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\ServiceDescriptor;
use Maduser\Argon\Container\Support\StringHelper;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use ReflectionClass;
use ReflectionException;

final class ServiceDefinitionGenerator
{
    public function __construct(
        private readonly ParameterExpressionResolver $parameterResolver
    ) {
    }

    /**
     * @throws ContainerException
     * @throws ReflectionException
     */
    public function generate(CompilationContext $context): void
    {
        $serviceMap = [];

        foreach ($context->container->getBindings() as $id => $descriptor) {
            if ($descriptor->shouldCompile() === false) {
                continue;
            }

            $methodName = $this->buildServiceMethodName($id);
            $concrete = $descriptor->getConcrete();

            if ($concrete instanceof Closure) {
                throw new ContainerException(
                    "Cannot compile a container with closures: [$id]. " .
                    "Use skipCompilation() to exclude from compilation."
                );
            }

            if ($descriptor->hasFactory()) {
                $this->compileFactoryService(
                    $context->namespace,
                    $context->class,
                    $id,
                    $methodName,
                    $descriptor,
                    $serviceMap
                );
                continue;
            }

            if (!(new ReflectionClass($concrete))->isInstantiable()) {
                $target = " [$concrete]";
                throw new ContainerException(
                    "Service [$id] points to non-instantiable class$target."
                );
            }

            $singletonProperty = "singleton_{$methodName}";

            $this->generateSingletonProperty($context->class, $singletonProperty, $id);
            $this->generateServiceMethod($context->class, $concrete, $id, $methodName, $singletonProperty);

            $serviceMap[$id] = $methodName;
        }

        $context->class->addProperty('serviceMap')->setValue($serviceMap);
    }

    /**
     * @throws ContainerException
     * @throws ReflectionException
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
        $args = [];

        assert($factoryClass !== null);

        $factoryReflection = new ReflectionClass($factoryClass);

        if ($factoryReflection->hasMethod($factoryMethod)) {
            $methodReflection = $factoryReflection->getMethod($factoryMethod);

            foreach ($methodReflection->getParameters() as $param) {
                $args[] = $this->parameterResolver->resolveParameter($param, $id, '$args');
            }
        }

        $argString = implode(",\n", $args);

        $fqFactory = '\\' . ltrim($factoryClass, '\\');
        $returnType = class_exists($id) ? '\\' . ltrim($id, '\\') : 'object';
        $singletonProperty = "singleton_{$methodName}";

        $namespace->addUse($factoryClass);

        $class->addProperty($singletonProperty)
            ->setPrivate()
            ->setType('?' . $returnType)
            ->setValue(null);

        $method = $class->addMethod($methodName);
        $method->setPrivate()
            ->setReturnType('object');

        $method->addParameter('args')
            ->setType('array')
            ->setDefaultValue([])
            ->setReference();

        $method->setBody(<<<PHP
            if (\$this->{$singletonProperty} === null) {
                \$factory = \$this->get({$fqFactory}::class, \$args);
                \$this->{$singletonProperty} = \$factory->{$factoryMethod}(
                    {$argString}
                );
            }
            return \$this->{$singletonProperty};
        PHP);

        $serviceMap[$id] = $methodName;
    }

    /**
     * @param class-string $concrete
     *
     * @throws ReflectionException
     */
    private function generateServiceMethod(
        ClassType $class,
        string $concrete,
        string $id,
        string $methodName,
        string $singletonProperty
    ): void {
        $fqcn = '\\' . ltrim($concrete, '\\');
        $args = $this->parameterResolver->resolveConstructorArguments($concrete, $id, '$args');
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

    private function generateSingletonProperty(ClassType $class, string $propertyName, string $id): void
    {
        $typeHint = class_exists($id) ? '\\' . ltrim($id, '\\') : 'object';
        $class->addProperty($propertyName)
            ->setPrivate()
            ->setType('?' . $typeHint)
            ->setValue(null);
    }

    private function buildServiceMethodName(string $id): string
    {
        return 'get_' . StringHelper::sanitizeIdentifier($id);
    }
}
