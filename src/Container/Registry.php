<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use ArrayAccess;
use Iterator;

/**
 * Class Registry
 *
 * A container class that implements ArrayAccess and Iterator,
 * allowing array-like access and iteration over stored items.
 *
 * @package Maduser\Argon
 */
class Registry implements ArrayAccess, Iterator
{
    /**
     * @var array The array of stored items in the container
     */
    protected array $items = [];

    /**
     * Registry constructor.
     *
     * @param array $items Initial items to be stored in the container
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /*======================
     * ArrayAccess Methods
     ======================*/

    /**
     * Checks if an offset exists in the container.
     *
     * @param mixed $offset The offset to check
     *
     * @return bool True if the offset exists, false otherwise
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * Retrieves the value at a given offset.
     *
     * @param mixed $offset The offset to retrieve
     *
     * @return mixed The value at the offset
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    /**
     * Sets a value at a given offset.
     *
     * @param mixed $offset The offset to set
     * @param mixed $value  The value to assign
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->items[$offset] = $value;
    }

    /**
     * Unsets a value at a given offset.
     *
     * @param mixed $offset The offset to unset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /*======================
     * Iterator Methods
     ======================*/

    /**
     * Returns the current element in the container.
     *
     * @return mixed The current element
     */
    public function current(): mixed
    {
        return current($this->items);
    }

    /**
     * Moves forward to the next element in the container.
     */
    public function next(): void
    {
        next($this->items);
    }

    /**
     * Returns the key of the current element.
     *
     * @return string|int|null The key of the current element
     */
    public function key(): string|int|null
    {
        return key($this->items);
    }

    /**
     * Checks if the current position is valid.
     *
     * @return bool True if the current position is valid, false otherwise
     */
    public function valid(): bool
    {
        return key($this->items) !== null;
    }

    /**
     * Rewinds the iterator to the first element.
     */
    public function rewind(): void
    {
        reset($this->items);
    }

    /*======================
     * Utility Methods
     ======================*/

    /**
     * Adds an item to the container.
     *
     * @param mixed $key   The key to assign the value
     * @param mixed $value The value to store
     */
    public function add(mixed $key, mixed $value): void
    {
        $this->items[$key] = $value;
    }

    /**
     * Retrieves an item from the container by key.
     *
     * @param mixed $key The key to retrieve the value for
     *
     * @return mixed The value at the key
     */
    public function get(mixed $key): mixed
    {
        return $this->items[$key] ?? null;
    }

    /**
     * Checks if a key exists in the container.
     *
     * @param mixed $key The key to check
     *
     * @return bool True if the key exists, false otherwise
     */
    public function has(mixed $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Sets all items in the container.
     *
     * @param array $items An array of items to set in the container
     */
    public function set(array $items): void
    {
        $this->items = $items;
    }

    /**
     * Retrieves all items in the container.
     *
     * @return array The array of all items
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Applies a callback to each item in the container.
     *
     * @param callable $callback The callback to apply
     *
     * @return static A new container with the transformed items
     */
    public function each(callable $callback): static
    {
        $container = new static();

        foreach ($this->items as $key => $item) {
            $container->add($key, $callback($item));
        }

        return $container;
    }
}
