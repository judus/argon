<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Compiler;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Compiler\ServiceInvocationGenerator;
use Maduser\Argon\Container\Support\StringHelper;
use Nette\PhpGenerator\ClassType;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Container\Compiler\Stubs\ServiceDependency;
use Tests\Unit\Container\Compiler\Stubs\ServiceWithTypedMethods;

final class ServiceInvocationGeneratorTest extends TestCase
{
    public function testGenerateCreatesInvokerWithCastsAndServiceReferences(): void
    {
        $container = new ArgonContainer();
        $container->set(ServiceWithTypedMethods::class)
            ->defineInvocation('handle', [
                'id' => '42',
                'ratio' => '3.5',
                'dependency' => '@dependency.service',
                'note' => 'test',
            ]);
        $container->set('dependency.service', static fn() => new ServiceDependency());

        $generator = new ServiceInvocationGenerator($container);
        $class = new ClassType('CompiledContainer');

        $generator->generate($class);

        $methodName = 'invoke_'
            . StringHelper::sanitizeIdentifier(ServiceWithTypedMethods::class)
            . '__handle';

        self::assertTrue($class->hasMethod($methodName));
        $method = $class->getMethod($methodName);
        self::assertSame('mixed', $method->getReturnType());

        $parameters = $method->getParameters();
        self::assertArrayHasKey('args', $parameters);
        $argsParameter = $parameters['args'];
        self::assertSame('array', $argsParameter->getType());
        self::assertTrue($argsParameter->hasDefaultValue());
        self::assertSame([], $argsParameter->getDefaultValue());

        $body = $method->getBody();
        $escapedServiceClass = str_replace('\\', '\\\\', ServiceWithTypedMethods::class);
        self::assertStringContainsString("\$controller = \$this->get('{$escapedServiceClass}');", $body);
        self::assertStringContainsString("\$this->get('dependency.service')", $body);
        self::assertStringContainsString("(int) \$mergedArgs['id']", $body);
        self::assertStringContainsString("(float) \$mergedArgs['ratio']", $body);
    }

    public function testGenerateSkipsPrimitiveCastsForNonClassAndMissingMethod(): void
    {
        $container = new ArgonContainer();
        $container->set('closure.service', static fn() => new ServiceWithTypedMethods())
            ->defineInvocation('handle', ['id' => 1]);
        $container->set(ServiceWithTypedMethods::class)
            ->defineInvocation('undefinedMethod', []);

        $generator = new ServiceInvocationGenerator($container);
        $class = new ClassType('CompiledContainer');

        $generator->generate($class);

        $closureInvokerName = 'invoke_'
            . StringHelper::sanitizeIdentifier('closure.service')
            . '__handle';
        $missingInvokerName = 'invoke_'
            . StringHelper::sanitizeIdentifier(ServiceWithTypedMethods::class)
            . '__undefinedMethod';

        self::assertTrue($class->hasMethod($closureInvokerName));
        self::assertTrue($class->hasMethod($missingInvokerName));
        $closureMethod = $class->getMethod($closureInvokerName);
        $missingMethod = $class->getMethod($missingInvokerName);

        $escapedServiceClass = str_replace('\\', '\\\\', ServiceWithTypedMethods::class);

        $closureBody = $closureMethod->getBody();
        self::assertStringNotContainsString('(int)', $closureBody);
        self::assertStringNotContainsString('(float)', $closureBody);

        $missingBody = $missingMethod->getBody();
        self::assertStringContainsString("\$controller = \$this->get('{$escapedServiceClass}');", $missingBody);
        self::assertStringContainsString("\$controller->undefinedMethod(...\$mergedArgs);", $missingBody);
    }
}
