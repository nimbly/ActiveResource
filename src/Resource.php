<?php

namespace Nimbly\ActiveResource;

use Nimbly\ActiveResource\Events\ResourceSavedEvent;
use Nimbly\ActiveResource\Events\ResourceSavingEvent;
use Nimbly\ActiveResource\Events\ResourceDeletedEvent;
use Nimbly\ActiveResource\Events\ResourceDeletingEvent;
use UnexpectedValueException;

abstract class Resource
{
	use HasResponseHeaders;

	/**
	 * The resource URI.
	 *
	 * The unique URI for this resource.
	 *
	 * @var string|null
	 */
	protected ?string $resource_uri = null;

	/**
	 * Properties that may be mass assigned in constructor or in `fill()` method.
	 *
	 * @var array<string>
	 */
	protected array $fillableProperties = [];

	/**
	 * Object properties that have been modified.
	 *
	 * @var array<string,mixed>
	 */
	protected array $modifiedProperties = [];

	/**
	 * Object properties that should be ignored when saving.
	 *
	 * @var array<string>
	 */
	protected array $excludedProperties = [];

	/**
	 * @param array<string,mixed> $properties
	 */
	public function __construct(
		protected array $properties = [])
	{
		$this->fill($properties);
	}

	/**
	 * Hydrate the instance properties.
	 *
	 * Hydrating an instance sets the properties directly
	 * and does not mark them as modified. This is especially
	 * useful if caching resources or resource properties.
	 *
	 * Alternatively, and a better approach, is to use the static
	 * `make` method.
	 *
	 * @param array<string,mixed>|object $properties
	 * @return void
	 */
	public function hydrate(array|object $properties): void
	{
		foreach( $properties as $property => $value ){

			$mutator = $this->getPropertyMutator($property);

			if( $mutator ){
				$value = \call_user_func($mutator, $value);
			}

			$this->properties[$property] = $value;
		}

		if( !$this->getResourceUri() ){

			$resource_identifier = ResourceManager::getResourceIdentifier($this::class);

			if( isset($this->properties[$resource_identifier]) ){
				$this->resource_uri = \sprintf(
					"%s/%s",
					ResourceManager::getResourceName($this::class),
					$this->properties[$resource_identifier]
				);
			}
		}

		$this->modifiedProperties = [];
	}

	/**
	 * Make a new instance with hydrated properties.
	 *
	 * @param array<string,mixed>|object $properties
	 * @param array<string,mixed> $responseHeaders
	 * @return static
	 */
	public static function make(
		array|object $properties,
		array $responseHeaders = []): static
	{
		$instance = new static;
		$instance->hydrate($properties);
		$instance->responseHeaders = $responseHeaders;
		return $instance;
	}

	/**
	 * Mass assign properties on resource.
	 *
	 * @param array<string,mixed> $properties
	 * @return void
	 */
	public function fill(array $properties): void
	{
		foreach( $properties as $property => $value ){
			if( \in_array($property, $this->fillableProperties) ){
				$this->setProperty($property, $value);
			}
		}
	}

	/**
	 * Get the modified properties.
	 *
	 * @return array<string,mixed>
	 */
	public function getModifiedProperties(): array
	{
		return $this->modifiedProperties;
	}

	/**
	 * Reset all modified properties on resource.
	 *
	 * @return void
	 */
	public function reset(): void
	{
		$this->modifiedProperties = [];
	}

	/**
	 * Magic getter for resource properties.
	 *
	 * @param string $property
	 * @return mixed|null
	 */
	public function __get(string $property)
	{
		return $this->getProperty($property);
	}

	/**
	 * Get a property by its name.
	 *
	 * @param string $property
	 * @return mixed|null
	 */
	public function getProperty(string $property): mixed
	{
		$value = $this->modifiedProperties[$property] ?? $this->properties[$property] ?? null;

		$accessor = $this->getPropertyAccessor($property);

		if( $accessor ){
			return \call_user_func($accessor, $value);
		}

		return $value;
	}

	/**
	 * Get the resource property's accessor, if any.
	 *
	 * @param string $property
	 * @return callable|null
	 */
	private function getPropertyAccessor(string $property): ?callable
	{
		$methodName = "get" . $this->toCamelCase($property) . "Property";

		if( \method_exists($this, $methodName) ){
			return [$this, $methodName];
		}

		return null;
	}

	/**
	 * Magic setter for resource properties.
	 *
	 * @param string $property
	 * @param mixed $value
	 * @return void
	 */
	public function __set(string $property, $value): void
	{
		$this->setProperty($property, $value);
	}

	/**
	 * Set a class property.
	 *
	 * @param string $property
	 * @param mixed $value
	 * @return void
	 */
	public function setProperty(string $property, mixed $value): void
	{
		$mutator = $this->getPropertyMutator($property);

		if( $mutator ){
			$value = \call_user_func($mutator, $value);
		}

		$this->modifiedProperties[$property] = $value;
	}

	/**
	 * Get the resource property's mutator, if any.
	 *
	 * @param string $property
	 * @return callable|null
	 */
	private function getPropertyMutator(string $property): ?callable
	{
		$methodName = "set" . $this->toCamelCase($property) . "Property";

		if( \method_exists($this, $methodName) ){
			return [$this, $methodName];
		}

		return null;
	}

	/**
	 * Convert a string into camel case.
	 *
	 * @param string $value
	 * @return string
	 */
	private function toCamelCase(string $value): string
	{
		$result = \preg_replace("/[^\w\d]|[_]/", " ", \trim($value));

		if( $result === null ){
			$result = $value;
		}

		$result = \preg_replace("/\s/", "", \ucwords($result));

		return $result ?? $value;
	}

	/**
	 * Get all properties.
	 *
	 * @return array<string,mixed>
	 */
	public function toArray(): array
	{
		return \array_merge(
			$this->properties,
			$this->modifiedProperties
		);
	}

	/**
	 * Get the URI for this resource.
	 *
	 * @return string|null
	 */
	public function getResourceUri(): ?string
	{
		return $this->resource_uri;
	}

	/**
	 * Find a resource by its ID.
	 *
	 * @param string|integer $id
	 * @param array<string,mixed> $query
	 * @param array<string,mixed> $headers
	 * @throws ResponseException
	 * @return static
	 */
	public static function find(
		string|int $id,
		array $query = [],
		array $headers = []): static
	{
		$resource_uri = ResourceManager::getResourceName(static::class) . "/" . (string) $id;

		$response = ConnectionManager::getInstance()->sendRequest(
			connection: ResourceManager::getConnectionName(static::class),
			method: "get",
			uri: $resource_uri,
			query: $query,
			headers: $headers
		);

		return static::make(
			$response->getPayload(),
			$response->getHeaders()
		);
	}

	/**
	 * Get all resources.
	 *
	 * @param array<string,mixed> $query
	 * @param array<string,mixed> $headers
	 * @throws ResponseException
	 * @return ResourceCollection<static>
	 */
	public static function all(
		array $query = [],
		array $headers = []): ResourceCollection
	{
		$resourceName = ResourceManager::getResourceName(static::class);

		$response = ConnectionManager::getInstance()->sendRequest(
			connection: ResourceManager::getConnectionName(static::class),
			method: "get",
			uri: $resourceName,
			query: $query,
			headers: $headers,
		);

		$collectionProperty = ResourceManager::getCollectionProperty(static::class);

		if( $collectionProperty ){

			if( !isset($response->getPayload()->{$collectionProperty}) ){
				throw new UnexpectedValueException(
					\sprintf(
						"Cannot create collection. No property named \"%s\" in payload.",
						$collectionProperty
					)
				);
			}

			$resources = $response->getPayload()->{$collectionProperty};
			$meta = \array_filter(
				(array) $response->getPayload(),
				function(string $property) use ($collectionProperty): bool {
					return $property !== $collectionProperty;
				},
				ARRAY_FILTER_USE_KEY
			);
		}
		else {
			$resources = $response->getPayload();
		}

		if( !\is_array($resources) ){
			throw new UnexpectedValueException(
				"Response payload is not an array."
			);
		}

		return new ResourceCollection(
			resources: \array_map(
				function(array|object $properties): static {
					return static::make($properties);
				},
				$resources,
			),
			meta: $meta ?? [],
			responseHeaders: $response->getHeaders(),
		);
	}

	/**
	 * Save the resource.
	 *
	 * @param array<string,mixed> $query
	 * @param array<string,mixed> $headers
	 * @throws ResponseException
	 * @return boolean
	 */
	public function save(array $query = [], array $headers = []): bool
	{
		$connection_name = ResourceManager::getConnectionName(static::class);

		$connection = ConnectionManager::getInstance()->getConnection($connection_name);

		if( $this->getResourceUri() ){
			$method = $connection->getOption(Connection::OPTION_UPDATE_METHOD) ?? "put";
		}
		else {
			$method = $connection->getOption(Connection::OPTION_CREATE_METHOD) ?? "post";
		}

		if( $connection->getOption(Connection::OPTION_UPDATE_DIFF) ){
			$body = $this->modifiedProperties;
		}
		else {
			$body = \array_merge($this->properties, $this->modifiedProperties);
		}

		// Filter out excluded properties from body.
		$body = \array_filter(
			$body,
			fn(string $property) => !\in_array($property, $this->excludedProperties),
			ARRAY_FILTER_USE_KEY
		);

		ConnectionManager::getInstance()->dispatch(
			new ResourceSavingEvent($this)
		);

		$response = ConnectionManager::getInstance()->sendRequest(
			connection: $connection,
			method: $method,
			uri: $this->getResourceUri() ?? ResourceManager::getResourceName(static::class),
			body: $body,
			query: $query,
			headers: $headers
		);

		// $this->properties = \array_replace_recursive(
		// 	$this->properties,
		// 	$this->modifiedProperties
		// );

		$this->hydrate($response->getPayload());
		$this->responseHeaders = $response->getHeaders();

		ConnectionManager::getInstance()->dispatch(
			new ResourceSavedEvent($this)
		);

		return true;
	}

	/**
	 * Delete this instance.
	 *
	 * @param array<string,mixed> $query
	 * @param array<string,mixed> $headers
	 * @throws ResponseException
	 * @return boolean
	 */
	public function delete(array $query = [], array $headers = []): bool
	{
		if( !$this->getResourceUri() ){
			throw new ResourceException(
				"Cannot delete this resource as it does not have a URI."
			);
		}

		ConnectionManager::getInstance()->dispatch(
			new ResourceDeletingEvent($this)
		);

		$response = ConnectionManager::getInstance()->sendRequest(
			connection: ResourceManager::getConnectionName($this::class),
			method: "delete",
			uri: $this->getResourceUri(),
			query: $query,
			headers: $headers
		);

		ConnectionManager::getInstance()->dispatch(
			new ResourceDeletedEvent($this)
		);

		$this->responseHeaders = $response->getHeaders();
		return true;
	}
}