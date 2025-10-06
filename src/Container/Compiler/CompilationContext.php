<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Compiler;

use Maduser\Argon\Container\ArgonContainer;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;

final class CompilationContext
{
    public function __construct(
        public readonly ArgonContainer $container,
        public readonly PhpFile $file,
        public readonly PhpNamespace $namespace,
        public readonly ClassType $class,
        public readonly bool $strictMode
    ) {
    }
}
