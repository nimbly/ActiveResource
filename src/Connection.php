<?php

namespace Nimbly\ActiveResource;

use UnexpectedValueException;

class Connection
{
	/**
	 * HTTP method to use when creating.
	 *
	 * Default: "post"
	 */
	public const OPTION_CREATE_METHOD = "create_method";

	/**
	 * HTTP method to use when updating.
	 *
	 * Default: "put"
	 */
	public const OPTION_UPDATE_METHOD = "update_method";

	/**
	 * Send just the properties that have changed when updating.
	 *
	 * Default: false
	 */
	public const OPTION_UPDATE_DIFF = "update_diff";

	/**
	 * HTTP version to use when sending requests.
	 *
	 * Default: "1.1"
	 */
	public const OPTION_HTTP_VERSION = "http_version";

	/**
	 * Serializer to use on request body when sending requests.
	 *
	 * Default: "json_encode"
	 */
	public const OPTION_SERIALIZER = "serializer";

	/**
	 * Deserializer to use when parsing response.
	 *
	 * Default: "json_decode"
	 */
	public const OPTION_DESERIALIZER = "deserializer";

	/**
	 * Connection default options.
	 *
	 * @var array<string,mixed>
	 */
	protected array $default_options = [
		self::OPTION_CREATE_METHOD => "post",
		self::OPTION_UPDATE_METHOD => "put",
		self::OPTION_UPDATE_DIFF => false,
		self::OPTION_HTTP_VERSION => "1.1",
		self::OPTION_SERIALIZER => "\json_encode",
		self::OPTION_DESERIALIZER => "\json_decode",
	];

	/**
	 * @param string $host
	 * @param array<string,mixed> $headers Default headers to include with each request.
	 * @param array<string,mixed> $query Default query parameters to include with each request.
	 * @param array<string,mixed> $options Override default options.
	 */
	public function __construct(
		protected string $host,
		protected array $headers = [],
		protected array $query = [],
		protected array $options = [])
	{
		$this->options = \array_merge($this->default_options, $options);
	}

	/**
	 * Get the hostname for this connection.
	 *
	 * @return string
	 */
	public function getHost(): string
	{
		return $this->host;
	}

	/**
	 * Get the default headers.
	 *
	 * @return array<string,mixed>
	 */
	public function getHeaders(): array
	{
		return $this->headers;
	}

	/**
	 * Get the default query params.
	 *
	 * @return array<string,mixed>
	 */
	public function getQuery(): array
	{
		return $this->query;
	}

	/**
	 * Get a connection option value.
	 *
	 * @param string $option The connection option name.
	 * @return mixed The connection option value. Returns `null` if option not set.
	 */
	public function getOption(string $option): mixed
	{
		return $this->options[$option] ?? null;
	}

	/**
	 * Serialize a request body.
	 *
	 * @param mixed $data
	 * @return string
	 */
	public function serialize(mixed $data): string
	{
		if( !isset($this->options[self::OPTION_SERIALIZER]) ||
			!\is_callable($this->options[self::OPTION_SERIALIZER]) ) {
			throw new UnexpectedValueException("Serializer is not callable.");
		}

		return \call_user_func(
			$this->options[self::OPTION_SERIALIZER],
			$data
		);
	}

	/**
	 * Deserialize a response body.
	 *
	 * @param string $data
	 * @return mixed
	 */
	public function deserialize(string $data): mixed
	{
		if( !isset($this->options[self::OPTION_DESERIALIZER]) ||
			!\is_callable($this->options[self::OPTION_DESERIALIZER]) ) {
			throw new UnexpectedValueException("Deserializer is not callable.");
		}

		return \call_user_func(
			$this->options[self::OPTION_DESERIALIZER],
			$data
		);
	}
}