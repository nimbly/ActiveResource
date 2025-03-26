<?php

namespace Nimbly\ActiveResource\Events;

use Nimbly\ActiveResource\Resource;

abstract class ResourceEventAbstract
{
	/**
	 * @param Resource $resource
	 */
	public function __construct(
		protected Resource $resource)
	{
	}

	/**
	 * Get the Resource instance for this event.
	 *
	 * @return Resource
	 */
	public function getResource(): Resource
	{
		return $this->resource;
	}
}