<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\TagManager;
use Maduser\Argon\Container\ArgonContainer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

class TagManagerTest extends TestCase
{
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ArgonContainer&MockObject $container;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ArgonContainer::class);
    }

    public function testTagAddsSingleAndMultipleTags(): void
    {
        $manager = new TagManager($this->container);

        $manager->tag('serviceA', ['tag1']);
        $manager->tag('serviceB', ['tag1', 'tag2']);

        $all = $manager->all();

        $this->assertArrayHasKey('tag1', $all);
        $this->assertArrayHasKey('tag2', $all);
        $this->assertEquals(['serviceA', 'serviceB'], $all['tag1']);
        $this->assertEquals(['serviceB'], $all['tag2']);
    }

    public function testTagPreventsDuplicates(): void
    {
        $manager = new TagManager($this->container);

        $manager->tag('serviceX', ['shared']);
        $manager->tag('serviceX', ['shared']);

        $this->assertSame(['serviceX'], $manager->all()['shared']);
    }

    public function testHasReturnsTrueIfTagHasServices(): void
    {
        $manager = new TagManager($this->container);

        $manager->tag('svc', ['cool']);

        $this->assertTrue($manager->has('cool'));
    }

    public function testHasReturnsFalseIfTagDoesNotExist(): void
    {
        $manager = new TagManager($this->container);

        $this->assertFalse($manager->has('nope'));
    }

    public function testHasReturnsFalseIfTagIsEmpty(): void
    {
        $manager = new TagManager($this->container);

        // Inject empty tag manually
        $reflection = new ReflectionClass($manager);
        $tags = $reflection->getProperty('tags');
        $tags->setAccessible(true);
        $tags->setValue($manager, ['ghost' => []]);

        $this->assertFalse($manager->has('ghost'));
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testGetTaggedResolvesAllTaggedServices(): void
    {
        $manager = new TagManager($this->container);

        $manager->tag('id1', ['tagged']);
        $manager->tag('id2', ['tagged']);

        $service1 = new stdClass();
        $service2 = new stdClass();

        $this->container->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['id1', [], $service1],
                ['id2', [], $service2],
            ]);

        $resolved = $manager->getTagged('tagged');

        $this->assertSame([$service1, $service2], $resolved);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testGetTaggedReturnsEmptyForUnknownTag(): void
    {
        $manager = new TagManager($this->container);

        $this->assertSame([], $manager->getTagged('nowhere'));
    }
}
