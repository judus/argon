<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\TagManagerInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use ReflectionException;

/**
 * Handles service tagging and retrieval by tag.
 */
final class TagManager implements TagManagerInterface
{
    /**
     * @var array<string, list<string>>
     */
    private array $tags = [];

    public function __construct(
        private readonly ServiceContainer $container
    ) {
    }

    /**
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        return $this->tags;
    }

    public function has(string $tag): bool
    {
        return isset($this->tags[$tag]) && !empty($this->tags[$tag]);
    }

    /**
     * Tags a service with one or more tags.
     *
     * @param string $id
     * @param list<string> $tags
     */
    public function tag(string $id, array $tags): void
    {
        foreach ($tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }

            if (!in_array($id, $this->tags[$tag], true)) {
                $this->tags[$tag][] = $id;
            }
        }
    }

    /**
     * @param string $tag
     * @return list<object>
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function getTagged(string $tag): array
    {
        if (!isset($this->tags[$tag]) || empty($this->tags[$tag])) {
            return [];
        }

        $taggedServices = [];

        foreach ($this->tags[$tag] as $serviceId) {
            $taggedServices[] = $this->container->get($serviceId);
        }

        return $taggedServices;
    }
}
