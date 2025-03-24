<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use ReflectionException;

class TagManager
{
    private array $tags = [];

    public function __construct(private readonly ServiceContainer $container)
    {
    }

    /**
     * @return array<string, array<string>>
     */
    public function all(): array
    {
        return $this->tags;
    }

    public function has(string $tag): bool
    {
        return isset($this->tags[$tag]) && count($this->tags[$tag]) > 0;
    }

    /**
     * Tags a service with one or more tags.
     *
     * @param string $id   The service identifier
     * @param array  $tags The tags to associate with the service
     */
    public function tag(string $id, array $tags): void
    {
        foreach ($tags as $tag) {
            if (!in_array($id, $this->tags[$tag] ?? [], true)) {
                $this->tags[$tag][] = $id;
            }
        }
    }

    /**
     * Retrieves all services associated with a tag.
     *
     * @param string $tag The tag to search for
     *
     * @return array The services associated with the given tag
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function getTagged(string $tag): array
    {
        $taggedServices = [];

        if (isset($this->tags[$tag])) {
            foreach ($this->tags[$tag] as $serviceId) {
                $taggedServices[] = $this->container->get($serviceId);
            }
        }

        return $taggedServices;
    }
}
