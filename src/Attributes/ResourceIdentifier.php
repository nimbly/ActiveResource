<?php

namespace Nimbly\ActiveResource\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ResourceIdentifier
{
	public function __construct(
		protected string $identifier)
	{
	}

	/**
	 * Get the resource identifier.
	 *
	 * @return string
	 */
	public function getIdentifier(): string
	{
		return $this->identifier;
	}
}