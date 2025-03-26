<?php

namespace Nimbly\ActiveResource;

class Response
{
	/**
	 * @param mixed $payload
	 * @param array $headers
	 */
	public function __construct(
		protected mixed $payload,
		protected array $headers,
	)
	{
		$this->headers = \array_map(
			fn(array $values): string => \implode(", ", $values),
			$headers
		);
	}

	/**
	 * Get the parsed payload of the response.
	 *
	 * @return mixed
	 */
	public function getPayload(): mixed
	{
		return $this->payload;
	}

	/**
	 * Get the response headers.
	 *
	 * @return array<string,string>
	 */
	public function getHeaders(): array
	{
		return $this->headers;
	}
}