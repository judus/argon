<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\TagManagerInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;

/**
 * Handles service tagging and retrieval by tag.
 */
final class TagManager implements TagManagerInterface
{
    /**
     * @var array<string, array<string, array<string, mixed>>>
     * Format: tag => [serviceId => [metaKey => metaValue]]
     */
    private array $tags = [];

    public function __construct(
        private readonly ArgonContainer $container
    ) {
    }

    /**
     * Returns all tags with their associated service IDs.
     *
     * @param bool $detailed If true, includes metadata.
     * @return array<string, list<string>|array<string, array<string, mixed>>>
     */
    public function all(bool $detailed = false): array
    {
        if ($detailed) {
            return $this->tags;
        }

        return array_map(function ($services) {
            return array_keys($services);
        }, $this->tags);
    }

    public function has(string $tag): bool
    {
        return isset($this->tags[$tag]) && !empty($this->tags[$tag]);
    }

    /**
     * Tags a service with one or more tags.
     *
     * @param string $id
     * @param array<int|string, string|array<string, mixed>> $tags
     */
    public function tag(string $id, array $tags): void
    {
        foreach ($tags as $tag => $meta) {
            if (is_int($tag)) {
                /** @var string $realTag */
                $realTag = $meta;
                $meta = [];
            } else {
                $realTag = $tag;
                $meta = is_array($meta) ? $meta : [];
            }

            if (!isset($this->tags[$realTag])) {
                $this->tags[$realTag] = [];
            }

            $this->tags[$realTag][$id] = $meta;
        }
    }

    /**
     * Returns a list of *service instances* for a given tag.
     *
     * @param string $tag
     * @return list<object>
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function getTagged(string $tag): array
    {
        return array_map(
            fn(string $id) => $this->container->get($id),
            $this->getTaggedIds($tag)
        );
    }

    /**
     * Returns a list of service IDs for a given tag.
     *
     * @param string $tag
     * @return list<string>
     */
    public function getTaggedIds(string $tag): array
    {
        return array_keys($this->tags[$tag] ?? []);
    }

    public function getTaggedMeta(string $tag): array
    {
        return $this->tags[$tag] ?? [];
    }
}
