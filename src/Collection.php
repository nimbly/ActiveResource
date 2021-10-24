<?php

namespace ActiveResource;


class Collection implements \IteratorAggregate, \Countable, \ArrayAccess
{
	/**
	 * @var array<Model>
	 */
	protected $objects = [];

	/**
	 * @var int
	 */
	protected $index = 0;

	/**
	 * Collection constructor.
	 * @param array<Model> $data
	 */
	public function __construct(array $data = [])
	{
		$this->objects = $data;
	}

	public function getIterator()
	{
		return new \ArrayIterator($this->objects);
	}

	public function first()
	{
		if( $this->offsetExists(0) ){
			return $this->offsetGet(0);
		}

		return null;
	}

	public function toArray()
	{
		$objects = [];
		foreach( $this->objects as $object ){
			if( $object instanceof Model ){
				$objects[] = $object->toArray();
			}
		}

		return $objects;
	}

	public function toJson(): ?string
	{
		return \json_encode($this->toArray());
	}

	public function current()
	{
		return $this->objects[$this->index];
	}

	public function seek($position)
	{
		if( !($this->offsetExists($position)) ){
			throw new \OutOfBoundsException('Offset does not exist');
		}

		$this->index = $position;
	}

	public function next()
	{
		$this->index++;
	}

	public function rewind()
	{
		$this->index = 0;
	}

	public function count()
	{
		return count($this->objects);
	}

	public function valid()
	{
		return (($this->index+1) > $this->count());
	}

	public function key()
	{
		return $this->index;
	}

	public function offsetGet($offset)
	{
		return $this->objects[$offset];
	}

	public function offsetExists($offset)
	{
		return (array_key_exists($offset, $this->objects));
	}

	public function offsetSet($offset, $value)
	{
		if( $offset == null ){
			$this->objects[] = $value;
		}
		else {
			$this->objects[$offset] = $value;
		}
	}

	public function offsetUnset($offset)
	{
		unset($this->objects[$offset]);
	}
}