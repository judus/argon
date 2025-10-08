<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Compiler;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use ReflectionException;

final class ContainerCompiler
{
    private CompilationContextFactory $contextFactory;
    private CoreContainerGenerator $coreGenerator;
    private ServiceDefinitionGenerator $serviceDefinitionGenerator;
    private ServiceInvocationGenerator $serviceInvocationGenerator;

    public function __construct(
        private readonly ArgonContainer $container,
        ?CompilationContextFactory $contextFactory = null,
        ?CoreContainerGenerator $coreGenerator = null,
        ?ServiceDefinitionGenerator $serviceDefinitionGenerator = null,
        ?ServiceInvocationGenerator $serviceInvocationGenerator = null,
        ?ParameterExpressionResolver $parameterResolver = null
    ) {
        $this->contextFactory = $contextFactory ?? new CompilationContextFactory();
        $this->coreGenerator = $coreGenerator ?? new CoreContainerGenerator($container);

        if ($serviceDefinitionGenerator === null) {
            $parameterResolver ??= new ParameterExpressionResolver($container, $container->getContextualBindings());
            $serviceDefinitionGenerator = new ServiceDefinitionGenerator($parameterResolver);
        }

        $this->serviceDefinitionGenerator = $serviceDefinitionGenerator;
        $this->serviceInvocationGenerator = $serviceInvocationGenerator ?? new ServiceInvocationGenerator($container);
    }

    /**
     * @throws ContainerException
     * @throws ReflectionException
     */
    public function compile(
        string $filePath,
        string $className,
        string $namespace = 'App\\Compiled',
        bool $strictMode = false,
        ?bool $noReflection = null
    ): void {
        if (!$strictMode) {
            $strictMode = $this->container->isStrictMode();
        }

        if ($noReflection === null) {
            $noReflection = $strictMode;
        }

        $context = $this->contextFactory->create(
            $this->container,
            $namespace,
            $className,
            $strictMode,
            $noReflection
        );

        $this->coreGenerator->generate($context);
        $this->serviceDefinitionGenerator->generate($context);
        $this->serviceInvocationGenerator->generate($context->class);

        $compiled = (string) $context->file;

        if (!file_exists($filePath) || md5_file($filePath) !== md5($compiled)) {
            file_put_contents($filePath, $compiled);
        }
    }
}
