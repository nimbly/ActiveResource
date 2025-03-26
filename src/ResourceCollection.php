<?php

namespace Nimbly\ActiveResource;

/**
 * The ResourceCollection represents a response when retrieving
 * multiple resources from an API.
 *
 * Eg: GET#/books?p=2
 * Eg: GET#/books?q=scifi
 */
class ResourceCollection extends Collection
{
	use HasResponseHeaders;

	/**
	 * @param array<Resource> $resources
	 * @param array<string,mixed> $meta
	 */
	public function __construct(
		array $resources = [],
		protected array $meta = [],
		array $responseHeaders = [],
	)
	{
		parent::__construct($resources);
		$this->responseHeaders = $responseHeaders;
	}

	/**
	 * Get the meta data for the resource list.
	 *
	 * @param string|null $property The meta data property name to return. If `null` returns all meta data.
	 * @return mixed
	 */
	public function getMeta(?string $property = null): mixed
	{
		if( $property ){
			return $this->meta[$property] ?? null;
		}

		return $this->meta;
	}
}