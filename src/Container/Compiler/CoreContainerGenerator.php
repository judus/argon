<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Compiler;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Nette\PhpGenerator\ClassType;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

final class CoreContainerGenerator
{
    private bool $strictMode = false;

    public function __construct(private readonly ArgonContainer $container)
    {
    }

    public function generate(CompilationContext $context): void
    {
        $this->strictMode = $context->strictMode;
        $class = $context->class;

        $this->generateConstructor($class);
        $this->generateCoreProperties($class);
        $this->generateMethodInvocationMap($class);
        $this->generateInterceptorMethods($class);
        $this->generateHasMethod($class);
        $this->generateGetMethod($class);
        $this->generateGetTaggedMethod($class);
        $this->generateGetTaggedIdsMethod($class);
        $this->generateGetTaggedMetaMethod($class);
        $this->generateInvokeMethod($class);
        $this->generateInvokeServiceMethod($class);
        $this->generateBuildCompiledInvokerMethodName($class);
    }

    private function generateConstructor(ClassType $class): void
    {
        $constructor = $class->addMethod('__construct')->setPublic();
        if ($this->strictMode) {
            $constructor->addBody('parent::__construct(strictMode: true);');
        } else {
            $constructor->addBody('parent::__construct();');
        }

        $parameterStore = $this->container->getParameters()->all();
        if (!empty($parameterStore)) {
            $formatted = var_export($parameterStore, true);
            $constructor->addBody("\$this->getParameters()->setStore({$formatted});");
        }

        $contextualBindings = $this->container->getContextualBindings()->getBindings();
        if (!empty($contextualBindings)) {
            $constructor->addBody('$contextual = $this->getContextualBindings();');

            foreach ($contextualBindings as $consumer => $dependencies) {
                foreach ($dependencies as $dependency => $concrete) {
                    if ($concrete instanceof \Closure) {
                        throw new ContainerException(sprintf(
                            'Cannot compile contextual binding for "%s" -> "%s": closures are not supported in compiled containers.',
                            $consumer,
                            $dependency
                        ));
                    }

                    $constructor->addBody(sprintf(
                        '$contextual->bind(%s, %s, %s);',
                        var_export($consumer, true),
                        var_export($dependency, true),
                        var_export($concrete, true)
                    ));
                }
            }
        }
    }

    private function generateCoreProperties(ClassType $class): void
    {
        $class->addProperty('tagMap')->setPrivate()->setValue($this->container->getTags(true));
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

    private function generateMethodInvocationMap(ClassType $class): void
    {
        $resolver = new ParameterExpressionResolver(
            $this->container,
            $this->container->getContextualBindings()
        );

        $bindings = $this->container->getContextualBindings()->getBindings();
        $methodMap = [];

        foreach ($bindings as $consumer => $_) {
            if (!str_contains($consumer, '::')) {
                continue;
            }

            [$service, $method] = explode('::', $consumer, 2);

            if (!class_exists($service)) {
                throw new ContainerException(sprintf(
                    'Contextual binding references missing class "%s".',
                    $service
                ));
            }

            $reflectionClass = new ReflectionClass($service);

            if (!$reflectionClass->hasMethod($method)) {
                throw new ContainerException(sprintf(
                    'Contextual binding references missing method "%s::%s".',
                    $service,
                    $method
                ));
            }

            $reflectionMethod = $reflectionClass->getMethod($method);
            $compiledMethodName = $this->buildCompiledMethodInvokerName($service, $method);

            $this->generateMethodInvoker(
                $class,
                $resolver,
                $service,
                $reflectionMethod,
                $consumer,
                $compiledMethodName
            );

            $methodMap[$consumer] = $compiledMethodName;
        }

        $class->addProperty('compiledMethodMap')
            ->setPrivate()
            ->setValue($methodMap);
    }

    private function generateMethodInvoker(
        ClassType $class,
        ParameterExpressionResolver $resolver,
        string $service,
        ReflectionMethod $method,
        string $contextKey,
        string $compiledMethodName
    ): void {
        $expressions = $resolver->resolveMethodParameters(
            $method,
            $service,
            $contextKey,
            '$args'
        );

        $arguments = implode(
            ",\n",
            $expressions
        );

        $compiled = $class->addMethod($compiledMethodName)
            ->setPrivate()
            ->setReturnType('mixed');

        $instanceParam = $compiled->addParameter('instance')
            ->setType('object')
            ->setNullable(true);
        $instanceParam->setDefaultValue(null);

        $compiled->addParameter('args')
            ->setType('array')
            ->setDefaultValue([]);

        if ($method->isStatic()) {
            $call = '\\' . ltrim($service, '\\') . '::' . $method->getName();
            $compiled->setBody(sprintf(
                'return %s(%s);',
                $call,
                trim($arguments) === '' ? '' : "\n{$arguments}\n"
            ));
            return;
        }

        $body = '$target = $instance ?? $this->get(' . var_export($service, true) . ");\n";
        $call = '$target->' . $method->getName();
        $body .= sprintf(
            'return %s(%s);',
            $call,
            trim($arguments) === '' ? '' : "\n{$arguments}\n"
        );

        $compiled->setBody($body);
    }

    private function buildCompiledMethodInvokerName(string $service, string $method): string
    {
        $sanitizedService = preg_replace('/[^A-Za-z0-9_]/', '_', $service);
        $sanitizedMethod = preg_replace('/[^A-Za-z0-9_]/', '_', $method);

        return 'call_' . $sanitizedService . '__' . $sanitizedMethod;
    }

    private function generateInterceptorMethods(ClassType $class): void
    {
        $pre = $class->addMethod('applyPreInterceptors');
        $pre->setPrivate()
            ->setReturnType('object')
            ->setReturnNullable(true);

        $pre->addParameter('id')->setType('string');
        $pre->addParameter('args')->setType('array')->setDefaultValue([])->setReference();

        $pre->setBody(<<<'PHP'
            foreach ($this->preInterceptors as $interceptor) {
                if (!$interceptor::supports($id)) {
                    continue;
                }

                $resolved = $this->get($interceptor);
                $result = $resolved->intercept($id, $args);
                if ($result !== null) {
                    return $result;
                }
            }
            return null;
        PHP);

        $post = $class->addMethod('applyPostInterceptors');
        $post->setPrivate()
            ->setReturnType('object');

        $post->addParameter('instance')->setType('object');

        $post->setBody(<<<'PHP'
            foreach ($this->postInterceptors as $interceptor) {
                if (!$interceptor::supports($instance)) {
                    continue;
                }

                $resolved = $this->get($interceptor);
                $resolved->intercept($instance);
            }
            return $instance;
        PHP);
    }

    private function generateGetMethod(ClassType $class): void
    {
        $method = $class->addMethod('get')
            ->setReturnType('object');

        $strictBody = <<<'PHP'
                $instance = $this->applyPreInterceptors($id, $args);
                if ($instance !== null) {
                    return $instance;
                }

                if (!isset($this->serviceMap[$id])) {
                    throw new NotFoundException($id, 'compiled');
                }

                $instance = $this->{$this->serviceMap[$id]}($args);

                return $this->applyPostInterceptors($instance);
        PHP;

        $lenientBody = <<<'PHP'
                $instance = $this->applyPreInterceptors($id, $args);
                if ($instance !== null) {
                    return $instance;
                }

                $instance = isset($this->serviceMap[$id])
                    ? $this->{$this->serviceMap[$id]}($args)
                    : parent::get($id, $args);

                return $this->applyPostInterceptors($instance);
        PHP;

        $method->setBody($this->strictMode ? $strictBody : $lenientBody);

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
            foreach (array_keys($this->tagMap[$tag]) as $id) {
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
            ->setBody('return array_keys($this->tagMap[$tag] ?? []);')
            ->addParameter('tag')->setType('string');
    }

    private function generateGetTaggedMetaMethod(ClassType $class): void
    {
        $class->addMethod('getTaggedMeta')
            ->setReturnType('array')
            ->setBody(<<<'PHP'
            return $this->tagMap[$tag] ?? [];
        PHP)
            ->addParameter('tag')->setType('string');
    }

    private function generateHasMethod(ClassType $class): void
    {
        $body = $this->strictMode
            ? 'return isset($this->serviceMap[$id]);'
            : 'return isset($this->serviceMap[$id]) || parent::has($id);';

        $class->addMethod('has')
            ->setReturnType('bool')
            ->setBody($body)
            ->addParameter('id')->setType('string');
    }

    private function generateInvokeMethod(ClassType $class): void
    {
        $invoke = $class->addMethod('invoke')
            ->setPublic()
            ->setReturnType('mixed');

        $invoke->addParameter('target')->setType('callable|object|array|string');
        $invoke->addParameter('arguments')->setType('array')->setDefaultValue([]);

        $lenientBody = <<<'PHP'
        if (is_array($target) && count($target) === 2) {
            [$controller, $method] = $target;
            $contextKey = (is_object($controller) ? get_class($controller) : $controller) . '::' . $method;
            $instance = is_object($controller) ? $controller : null;

            if (isset($this->compiledMethodMap[$contextKey])) {
                return $this->{$this->compiledMethodMap[$contextKey]}($instance, $arguments);
            }

            $instance = is_object($controller) ? $controller : $this->get($controller);
            $reflection = new \ReflectionMethod($instance, $method);
        } elseif (is_string($target) && str_contains($target, '::')) {
            [$controller, $method] = explode('::', $target, 2);
            $contextKey = $controller . '::' . $method;

            if (isset($this->compiledMethodMap[$contextKey])) {
                return $this->{$this->compiledMethodMap[$contextKey]}(null, $arguments);
            }

            $instance = $this->get($controller);
            $reflection = new \ReflectionMethod($instance, $method);
        } else {
            if (is_callable($target) && !is_array($target)) {
                $reflection = new \ReflectionFunction($target);
                $instance = null;
            } elseif (is_array($target) && count($target) === 2) {
                [$controller, $method] = $target;
                $instance = is_object($controller) ? $controller : $this->get($controller);
                $reflection = new \ReflectionMethod($instance, $method);
            } else {
                $instance = is_object($target) ? $target : $this->get($target);
                $contextKey = get_class($instance) . '::__invoke';

                if (isset($this->compiledMethodMap[$contextKey])) {
                    return $this->{$this->compiledMethodMap[$contextKey]}($instance, $arguments);
                }

                $reflection = new \ReflectionMethod($instance, '__invoke');
            }
        }

        $params = [];

        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType()?->getName();

            if (array_key_exists($name, $arguments)) {
                $params[] = $arguments[$name];
                continue;
            }

            if ($type && $this->has($type)) {
                $params[] = $this->get($type);
                continue;
            }

            if ($type && class_exists($type)) {
                if ($param->allowsNull()) {
                    $params[] = null;
                    continue;
                }

                $params[] = $this->get($type);
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
                continue;
            }

            if ($param->allowsNull()) {
                $params[] = null;
                continue;
            }

            throw new \RuntimeException('Unable to resolve parameter ' . $name . ' for ' . $reflection->getName());
        }

        return $reflection->invokeArgs($instance, $params);
    PHP;

        $strictBody = <<<'PHP'
        if (is_array($target) && count($target) === 2) {
            [$controller, $method] = $target;
            $contextKey = (is_object($controller) ? get_class($controller) : $controller) . '::' . $method;
            $instance = is_object($controller) ? $controller : null;

            if (isset($this->compiledMethodMap[$contextKey])) {
                return $this->{$this->compiledMethodMap[$contextKey]}($instance, $arguments);
            }

            $instance = is_object($controller) ? $controller : $this->get($controller);
            $reflection = new \ReflectionMethod($instance, $method);
        } elseif (is_string($target) && str_contains($target, '::')) {
            [$controller, $method] = explode('::', $target, 2);
            $contextKey = $controller . '::' . $method;

            if (isset($this->compiledMethodMap[$contextKey])) {
                return $this->{$this->compiledMethodMap[$contextKey]}(null, $arguments);
            }

            $instance = $this->get($controller);
            $reflection = new \ReflectionMethod($instance, $method);
        } else {
            if (is_callable($target) && !is_array($target)) {
                $reflection = new \ReflectionFunction($target);
                $instance = null;
            } elseif (is_array($target) && count($target) === 2) {
                [$controller, $method] = $target;
                $instance = is_object($controller) ? $controller : $this->get($controller);
                $reflection = new \ReflectionMethod($instance, $method);
            } else {
                $instance = is_object($target) ? $target : $this->get($target);
                $contextKey = get_class($instance) . '::__invoke';

                if (isset($this->compiledMethodMap[$contextKey])) {
                    return $this->{$this->compiledMethodMap[$contextKey]}($instance, $arguments);
                }

                $reflection = new \ReflectionMethod($instance, '__invoke');
            }
        }

        $params = [];

        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType()?->getName();

            if (array_key_exists($name, $arguments)) {
                $params[] = $arguments[$name];
                continue;
            }

            if ($type && $this->has($type)) {
                $params[] = $this->get($type);
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
                continue;
            }

            if ($param->allowsNull()) {
                $params[] = null;
                continue;
            }

            throw new NotFoundException($name, 'compiled invoke');
        }

        return $reflection->invokeArgs($instance, $params);
    PHP;

        $invoke->setBody($this->strictMode ? $strictBody : $lenientBody);
    }

    private function generateInvokeServiceMethod(ClassType $class): void
    {
        $method = $class->addMethod('invokeServiceMethod')
            ->setPrivate()
            ->setReturnType('mixed')
            ->setBody($this->strictMode
                ? <<<'PHP'
            $compiledMethod = $this->buildCompiledInvokerMethodName($serviceId, $method);

            if (method_exists($this, $compiledMethod)) {
                return $this->{$compiledMethod}($args);
            }

            throw new NotFoundException($serviceId, 'compiled invoke');
        PHP
                : <<<'PHP'
            $compiledMethod = $this->buildCompiledInvokerMethodName($serviceId, $method);

            if (method_exists($this, $compiledMethod)) {
                return $this->{$compiledMethod}($args);
            }

            return $this->invoke([$serviceId, $method], $args);
        PHP
            );

        $method->addParameter('serviceId')->setType('string');
        $method->addParameter('method')->setType('string');
        $method->addParameter('args')->setType('array')->setDefaultValue([]);
    }

    private function generateBuildCompiledInvokerMethodName(ClassType $class): void
    {
        $method = $class->addMethod('buildCompiledInvokerMethodName')
            ->setPrivate()
            ->setReturnType('string');

        $method->addParameter('serviceId')->setType('string');
        $method->addParameter('method')->setType('string')->setDefaultValue('__invoke');

        $method->setBody(<<<'PHP'
            $sanitizedService = preg_replace('/[^A-Za-z0-9_]/', '_', $serviceId);
            $sanitizedMethod  = preg_replace('/[^A-Za-z0-9_]/', '_', $method);
        
            return 'invoke_' . $sanitizedService . '__' . $sanitizedMethod;
    PHP);
    }
}
