<?php

namespace Nimbly\ActiveResource;

trait HasResponseHeaders
{
	protected array $responseHeaders = [];

	/**
	 * Get all response headers.
	 *
	 * @return array<string,string>
	 */
	public function getResponseHeaders(): array
	{
		return $this->responseHeaders;
	}

	/**
	 * Get a specific response header.
	 *
	 * @param string $header
	 * @return string|null
	 */
	public function getResponseHeader(string $header): ?string
	{
		foreach( $this->responseHeaders as $responseHeader => $value ){
			if( \strtolower($responseHeader) === \strtolower($header) ){
				return $value;
			}
		}

		return null;
	}
}