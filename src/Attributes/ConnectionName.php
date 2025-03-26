<?php

namespace Nimbly\ActiveResource\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ConnectionName
{
	public function __construct(
		protected string $name)
	{
	}

	/**
	 * Get the connection name.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}
}