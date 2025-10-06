<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Compiler;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Nette\PhpGenerator\PhpFile;

final class CompilationContextFactory
{
    public function create(
        ArgonContainer $container,
        string $namespace,
        string $className,
        bool $strictMode = false
    ): CompilationContext {
        $file = new PhpFile();
        $file->setStrictTypes();

        $namespaceGen = $file->addNamespace($namespace);
        $namespaceGen->addUse(ArgonContainer::class);
        $namespaceGen->addUse(ContainerException::class);
        $namespaceGen->addUse(NotFoundException::class);

        $class = $namespaceGen->addClass($className);
        $class->setExtends(ArgonContainer::class);

        return new CompilationContext($container, $file, $namespaceGen, $class, $strictMode);
    }
}
