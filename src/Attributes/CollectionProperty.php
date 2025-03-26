<?php

namespace Nimbly\ActiveResource\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class CollectionProperty
{
	/**
	 * @param string $collection_property
	 * @param boolean $index_by_identifier Index the collection by the resource identifier instead of numeric array index.
	 */
	public function __construct(
		protected string $collection_property,
		protected bool $index_by_identifier = false,
	)
	{
	}

	/**
	 * Get the collection property name.
	 *
	 * @return string
	 */
	public function getCollectionProperty(): string
	{
		return $this->collection_property;
	}

	/**
	 * Get the index by identifier option.
	 *
	 * @return boolean
	 */
	public function getIndexByIdentifier(): bool
	{
		return $this->index_by_identifier;
	}
}