<?php

namespace Nimbly\ActiveResource\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ResourceName
{
	public function __construct(
		protected string $name)
	{
	}

	/**
	 * Get the resource name.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}
}