<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Compiler;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Nette\PhpGenerator\PhpFile;

final class CompilationContextFactory
{
    public function create(
        ArgonContainer $container,
        string $namespace,
        string $className
    ): CompilationContext {
        $file = new PhpFile();
        $file->setStrictTypes();

        $namespaceGen = $file->addNamespace($namespace);
        $namespaceGen->addUse(ArgonContainer::class);
        $namespaceGen->addUse(ContainerException::class);

        $class = $namespaceGen->addClass($className);
        $class->setExtends(ArgonContainer::class);

        return new CompilationContext($container, $file, $namespaceGen, $class);
    }
}
