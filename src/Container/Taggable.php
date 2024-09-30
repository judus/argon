<?php

namespace Maduser\Argon\Container;

trait Taggable
{
    private array $tags = [];

    /**
     * Tags a service with one or more tags.
     *
     * @param string $service The service to tag
     * @param array  $tags    The tags to add to the service
     */
    public function tag(string $service, array $tags): void
    {
        foreach ($tags as $tag) {
            $this->tags[$tag][] = $service;
        }
    }

    /**
     * Retrieves all services associated with a tag.
     *
     * @param string $tag The tag to search for
     *
     * @return array The services associated with the given tag
     */
    public function tagged(string $tag): array
    {
        $taggedServices = [];

        if (isset($this->tags[$tag])) {
            foreach ($this->tags[$tag] as $service) {
                $taggedServices[] = $this->findAny($service);
            }
        }

        return $taggedServices;
    }
}