<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Compiler;

use Maduser\Argon\Container\ArgonContainer;
use Nette\PhpGenerator\ClassType;

final class CoreContainerGenerator
{
    public function __construct(private readonly ArgonContainer $container)
    {
    }

    public function generate(CompilationContext $context): void
    {
        $class = $context->class;

        $this->generateConstructor($class);
        $this->generateCoreProperties($class);
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
        $constructor->addBody('parent::__construct();');

        $parameterStore = $this->container->getParameters()->all();
        if (!empty($parameterStore)) {
            $formatted = var_export($parameterStore, true);
            $constructor->addBody("\$this->getParameters()->setStore({$formatted});");
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
        $class->addMethod('has')
            ->setReturnType('bool')
            ->setBody('return isset($this->serviceMap[$id]) || parent::has($id);')
            ->addParameter('id')->setType('string');
    }

    private function generateInvokeMethod(ClassType $class): void
    {
        $invoke = $class->addMethod('invoke')
            ->setPublic()
            ->setReturnType('mixed');

        $invoke->addParameter('target')->setType('callable|object|array|string');
        $invoke->addParameter('arguments')->setType('array')->setDefaultValue([]);

        $invoke->setBody(<<<'PHP'
        if (is_callable($target) && !is_array($target)) {
            $reflection = new \ReflectionFunction($target);
            $instance = null;
        } elseif (is_array($target) && count($target) === 2) {
            [$controller, $method] = $target;
            $instance = is_object($controller) ? $controller : $this->get($controller);
            $reflection = new \ReflectionMethod($instance, $method);
        } else {
            $instance = is_object($target) ? $target : $this->get($target);
            $reflection = new \ReflectionMethod($instance, '__invoke');
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

            throw new \RuntimeException("Unable to resolve parameter '{$name}' for '{$reflection->getName()}'");
        }

        return $reflection->invokeArgs($instance, $params);
    PHP);
    }

    private function generateInvokeServiceMethod(ClassType $class): void
    {
        $method = $class->addMethod('invokeServiceMethod')
            ->setPrivate()
            ->setReturnType('mixed')
            ->setBody(<<<'PHP'
            $compiledMethod = $this->buildCompiledInvokerMethodName($serviceId, $method);

            if (method_exists($this, $compiledMethod)) {
                return $this->{$compiledMethod}($args);
            }

            return $this->invoke([$serviceId, $method], $args);
        PHP);

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
