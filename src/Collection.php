<?php

namespace Nimbly\ActiveResource;

use Iterator;
use Countable;
use ArrayAccess;

/**
 * A generic collection class that can be used to do basic array
 * operations like filter, map, reduce, sort, etc. in a chainable
 * functional way. You can also iterate over the class in for and
 * foreach loops and directly access elements in the array.
 */
class Collection implements Iterator, ArrayAccess, Countable
{
	protected int $index = 0;

	public function __construct(
		protected array $items
	)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function current(): mixed
	{
		return $this->items[$this->index];
	}

	/**
	 * @inheritDoc
	 */
	public function key(): mixed
	{
		return $this->index;
	}

	/**
	 * @inheritDoc
	 */
	public function next(): void
	{
		$this->index++;
	}

	/**
	 * @inheritDoc
	 */
	public function rewind(): void
	{
		$this->index = 0;
	}

	/**
	 * @inheritDoc
	 */
	public function valid(): bool
	{
		return $this->offsetExists($this->index);
	}

	/**
	 * @inheritDoc
	 */
	public function offsetExists(mixed $offset): bool
	{
		return $offset < \count($this->items);
	}

	/**
	 * @inheritDoc
	 */
	public function offsetSet(mixed $offset, mixed $value): void
	{
		$this->items[$offset] = $value;
	}

	/**
	 * @inheritDoc
	 */
	public function offsetGet(mixed $offset): mixed
	{
		return $this->items[$offset];
	}

	/**
	 * @inheritDoc
	 */
	public function offsetUnset(mixed $offset): void
	{
		unset($this->items[$offset]);
	}

	/**
	 * @inheritDoc
	 */
	public function count(): int
	{
		return \count($this->items);
	}

	/**
	 * Get the resources.
	 *
	 * @return array<Resource>
	 */
	public function toArray(): array
	{
		return $this->items;
	}

	/**
	 * Filter out results from array.
	 *
	 * @param callable $callback
	 * @param integer $mode
	 * @return Collection<Resource>
	 */
	public function filter(callable $callback, int $mode = 0): Collection
	{
		return new Collection(
			\array_filter($this->items, $callback, $mode)
		);
	}

	/**
	 * Map items in collection to a new value.
	 *
	 * @param callable $callback
	 * @return Collection
	 */
	public function map(callable $callback): Collection
	{
		return new Collection(
			\array_map($callback, $this->items)
		);
	}

	/**
	 * Reduce the items to a single value.
	 *
	 * @param callable $callback
	 * @param mixed $initial
	 * @return mixed
	 */
	public function reduce(callable $callback, mixed $initial = null): mixed
	{
		return \array_reduce($this->items, $callback, $initial);
	}

	/**
	 * Take a slice or subset of the array.
	 *
	 * @param integer $offset
	 * @param integer|null $length
	 * @param boolean $preserve_keys
	 * @return Collection<Resource>
	 */
	public function slice(int $offset, ?int $length = null, bool $preserve_keys = false): Collection
	{
		return new Collection(
			\array_slice($this->items, $offset, $length, $preserve_keys)
		);
	}

	/**
	 * Find a specific item in the collection.
	 *
	 * @param string $property
	 * @param mixed $value
	 * @return mixed
	 */
	public function find(string $property, mixed $value): mixed
	{
		foreach( $this->items as $item ){
			if( $item->{$property} === $value ){
				return $item;
			}
		}

		return null;
	}

	/**
	 * Index the collection based on a particular property.
	 *
	 * @param string $property
	 * @return Collection
	 */
	public function index(string $property): Collection
	{
		$results = [];

		foreach( $this->items as $item ){
			$results[$item->{$property}] = $item;
		}

		return new Collection($results);
	}

	/**
	 * Sort items in array with a custom function.
	 *
	 * @param callable $callback
	 * @return Collection
	 */
	public function sort(callable $callback): Collection
	{
		$items = $this->toArray();
		\uasort($items, $callback);
		return new Collection($items);
	}
}