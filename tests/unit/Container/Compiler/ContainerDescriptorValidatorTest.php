<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Compiler;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Compiler\ContainerDescriptorValidator;
use Maduser\Argon\Container\Contracts\ServiceDescriptorInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Unit\Container\Compiler\Stubs\PrivateFactory;
use Tests\Unit\Container\Compiler\Stubs\ServiceWithTypedMethods;

final class ContainerDescriptorValidatorTest extends TestCase
{
    public function testFactoryBindingRequiresFactoryClass(): void
    {
        $descriptor = $this->createMock(ServiceDescriptorInterface::class);
        $descriptor->method('shouldCompile')->willReturn(true);
        $descriptor->method('getConcrete')->willReturn(stdClass::class);
        $descriptor->method('hasFactory')->willReturn(true);
        $descriptor->method('getFactoryClass')->willReturn(null);
        $descriptor->method('getFactoryMethod')->willReturn('__invoke');
        $descriptor->method('getInvocationMap')->willReturn([]);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Factory class not defined.');

        (new ContainerDescriptorValidator())->validate($this->containerWithDescriptor($descriptor));
    }

    public function testFactoryMethodMustBePublic(): void
    {
        $descriptor = $this->createMock(ServiceDescriptorInterface::class);
        $descriptor->method('shouldCompile')->willReturn(true);
        $descriptor->method('getConcrete')->willReturn(stdClass::class);
        $descriptor->method('hasFactory')->willReturn(true);
        $descriptor->method('getFactoryClass')->willReturn(PrivateFactory::class);
        $descriptor->method('getFactoryMethod')->willReturn('create');
        $descriptor->method('getInvocationMap')->willReturn([]);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            'Factory method "create" on class "' . PrivateFactory::class . '" is not public.'
        );

        (new ContainerDescriptorValidator())->validate($this->containerWithDescriptor($descriptor));
    }

    public function testInvocationMethodMustExist(): void
    {
        $container = new ArgonContainer();
        $container->set(ServiceWithTypedMethods::class)
            ->defineInvocation('missingMethod', []);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(sprintf(
            'Invocation method "missingMethod" not found on class "%s".',
            ServiceWithTypedMethods::class
        ));

        (new ContainerDescriptorValidator())->validate($container);
    }

    public function testInvocationMethodMustBePublic(): void
    {
        $container = new ArgonContainer();
        $container->set(PrivateFactory::class)
            ->defineInvocation('create', []);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(sprintf(
            'Invocation method "create" on class "%s" is not public.',
            PrivateFactory::class
        ));

        (new ContainerDescriptorValidator())->validate($container);
    }

    private function containerWithDescriptor(ServiceDescriptorInterface $descriptor): ArgonContainer
    {
        return new class ($descriptor) extends ArgonContainer {
            public function __construct(private readonly ServiceDescriptorInterface $descriptor)
            {
                parent::__construct();
            }

            #[\Override]
            public function getBindings(): array
            {
                return ['service' => $this->descriptor];
            }
        };
    }
}
