<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;

/**
 * @internal
 * Handles service tagging and retrieval by tag.
 */
interface TagManagerInterface
{
    /**
     * @return array<string, list<string>>
     */
    public function all(): array;

    public function has(string $tag): bool;

    /**
     * Tags a service with one or more tags.
     *
     * @param string $id
     * @param list<string> $tags
     */
    public function tag(string $id, array $tags): void;

    /**
     * @param string $tag
     * @return list<object>
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function getTagged(string $tag): array;

    /**
     * @param string $tag
     * @return list<string>
     */
    public function getTaggedIds(string $tag): array;
}
