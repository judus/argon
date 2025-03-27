<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Compiler;

use Maduser\Argon\Container\Contracts\InterceptorInterface;
use Maduser\Argon\Container\ServiceContainer;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use ReflectionClass;
use ReflectionNamedType;

class ContainerCompiler
{
    private ServiceContainer $container;

    public function __construct(ServiceContainer $container)
    {
        $this->container = $container;
    }

    public function compile(
        string $outputFile,
        string $className = 'CachedContainer',
        string $namespace = ''
    ): void {
        $bindings     = $this->container->getBindings();
        $parameters   = $this->container->getParameters()->all();
        $tags         = $this->container->getTags();
        $interceptors = $this->container->getInterceptors();

        $services = [];

        foreach ($bindings as $id => $descriptor) {
            $concrete = $descriptor->getConcrete();
            if ($concrete instanceof \Closure) {
                continue;
            }

            $fqcn = $concrete;
            $isSingleton = $descriptor->isSingleton();
            $args = [];

            $ref = new ReflectionClass($fqcn);
            if (!$ref->isInstantiable()) {
                continue;
            }

            $paramOverrides = $parameters[$fqcn] ?? [];

            if ($constructor = $ref->getConstructor()) {
                foreach ($constructor->getParameters() as $param) {
                    $type = $param->getType();
                    $name = $param->getName();

                    if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                        $args[] = "\$this->get('{$type->getName()}')";
                    } elseif (array_key_exists($name, $paramOverrides)) {
                        $args[] = var_export($paramOverrides[$name], true);
                    } elseif ($param->isDefaultValueAvailable()) {
                        $args[] = var_export($param->getDefaultValue(), true);
                    } else {
                        $args[] = 'null';
                    }
                }
            }

            $services[$id] = [$fqcn, $args, $isSingleton];
        }

        $this->compileInternal($outputFile, $services, $tags, $parameters, $namespace, $className, $interceptors);
    }

    private function compileInternal(
        string $outputFile,
        array $services,
        array $tags,
        array $parameters,
        string $namespace,
        string $className,
        array $interceptors
    ): void {
        $file = new PhpFile();
        $file->setStrictTypes();

        $ns = $namespace ? new PhpNamespace($namespace) : new PhpNamespace('');
        $ns->addUse(ServiceContainer::class);
        $ns->addUse('Maduser\Argon\Container\Exceptions\NotFoundException');

        $class = $ns->addClass($className)->setExtends(ServiceContainer::class);
        $class->addProperty('tags', $tags)->setProtected();
        $class->addProperty('singletons', [])->setProtected();
        $class->addProperty('compiledParameters', $parameters)->setProtected();

        $class->addMethod('__construct')
            ->setPublic()
            ->setBody("parent::__construct();\n\$this->getParameters()->setParameters(\$this->compiledParameters);");

        // Interceptors
        $interceptMap = [];
        foreach ($services as $id => [$fqcn, , ]) {
            foreach ($interceptors as $interceptorClass) {
                if (is_subclass_of($interceptorClass, InterceptorInterface::class) && method_exists($interceptorClass, 'supports')) {
                    if ($interceptorClass::supports($fqcn)) {
                        $method = 'interceptWith' . str_replace(['\\', '/'], '', $interceptorClass);
                        $interceptMap[$id] = [
                            'fqcn' => $fqcn,
                            'method' => $method,
                            'interceptor' => $interceptorClass
                        ];

                        if (!$class->hasMethod($method)) {
                            $m = $class->addMethod($method)
                                ->setPrivate()
                                ->setReturnType($fqcn);
                            $m->addParameter('instance')->setType($fqcn);
                            $m->setBody("\$interceptor = new \\{$interceptorClass}();\n\$interceptor->intercept(\$instance);\nreturn \$instance;");
                        }
                    }
                }
            }
        }

        // get()
        $get = $class->addMethod('get')
            ->setPublic()
            ->addComment('@throws NotFoundException')
            ->setReturnType('object');
        $get->addParameter('id')->setType('string');
        $get->setBody("return match (\$id) {\n" .
            implode("\n", array_map(fn($id) => "    '$id' => \$this->" . self::methodNameFromClass($id) . "(),", array_keys($services))) . "\n" .
            "    default => parent::get(\$id),\n};");

        // has()
        $has = $class->addMethod('has')
            ->setPublic()
            ->setReturnType('bool');
        $has->addParameter('id')->setType('string');
        $has->setBody('return in_array($id, [' . implode(', ', array_map(fn($id) => "'$id'", array_keys($services))) . '], true) || parent::has($id);');

        // getTagged()
        $class->addMethod('getTagged')
            ->setPublic()
            ->setBody(<<<'PHP'
                $taggedServices = [];

                if (isset($this->tags[$tag])) {
                    foreach ($this->tags[$tag] as $serviceId) {
                        $taggedServices[] = $this->get($serviceId);
                    }
                }

                return $taggedServices;
                PHP)
            ->setReturnType('array')
            ->addParameter('tag')->setType('string');

        // Per-service methods
        foreach ($services as $id => [$fqcn, $args, $singleton]) {
            $methodName = self::methodNameFromClass($id);
            $method = $class->addMethod($methodName)
                ->setPrivate()
                ->setReturnType($fqcn);

            $argsList = implode(', ', $args);
            $body = "\$instance = new \\{$fqcn}($argsList);";

            if (isset($interceptMap[$id])) {
                $body .= "\n\$instance = \$this->" . $interceptMap[$id]['method'] . "(\$instance);";
            }

            if ($singleton) {
                $body = "if (isset(\$this->singletons['$id'])) return \$this->singletons['$id'];\n" .
                    $body . "\n" .
                    "\$this->singletons['$id'] = \$instance;\n" .
                    "return \$instance;";
            } else {
                $body .= "\nreturn \$instance;";
            }

            $method->setBody($body);
        }

        $file->addNamespace($ns);
        file_put_contents($outputFile, (string) $file);
    }

    private static function methodNameFromClass(string $fqcn): string
    {
        return 'get_' . preg_replace('/[^A-Za-z0-9_]/', '_', $fqcn);
    }
}
