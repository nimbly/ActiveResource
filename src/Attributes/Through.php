<?php

namespace Nimbly\ActiveResource\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Through
{
	public function __construct(
		protected string $path)
	{
	}

	/**
	 * Get the path template.
	 *
	 * @return string
	 */
	public function getPath(): string
	{
		return $this->path;
	}
}