<?php

namespace ActiveResource;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

abstract class Model
{
	/**
	 * The primary key property for the resource.
	 *
	 * Defaults to "id".
	 *
	 * @var string
	 */
	protected $identifierName = "id";

	/**
	 * The name of the resource URI.
	 *
	 * Defaults to the lowercase name of the Model class
	 *
	 * For example, if the resource endpoint is "books", name the class Books or
	 * set this property on the class to "books."
	 *
	 * @var string
	 */
	protected $resourceName;

	/**
	 * The connection name to use for this resource
	 *
	 * @var string
	 */
	protected $connectionName = "default";

	/**
	 * Array of property names that are read only. I.e. when setting named property, it will not modify.
	 *
	 * When set to null or empty array, all properties are writeable.
	 *
	 * @var array<string>|null
	 */
	protected $readOnlyProperties;

	/**
	 * When set to array of property names, only these properties are allowed to be mass assigned when calling the fill() method.
	 *
	 * If null, *all* properties can be mass assigned.
	 *
	 * @var array<string>|null
	 */
	protected $fillableProperties;

	/**
	 * Array of property names that are excluded when saving/updating model to API.
	 *
	 * If null or empty array, all properties will be sent when saving model.
	 *
	 * @var array<string>|null
	 */
	protected $excludedProperties;

	/**
	 * Resource Identifier
	 *
	 * @var mixed
	 */
	private $resourceIdentifier;

	/**
	 * Model properties (attributes)
	 *
	 * @var array<string,mixed>
	 */
	private $properties = [];

	/**
	 * Modified model properties (attributes)
	 *
	 * @var array<string, mixed>
	 */
	private $modifiedProperties = [];

	/**
	 * Dependent resources
	 *
	 * @var array
	 */
	private $dependentResources = [];

	/**
	 * Model constructor.
	 *
	 * @param array<string,mixed> $data
	 */
	public function __construct(array $data = [])
	{
		if( !empty($data) ){
			$this->fill($data);
		}
	}

	/**
	 * Get the identifier property name (defaults to "id")
	 *
	 * @return string
	 */
	protected function getIdentifierName(): string
	{
		return $this->identifierName;
	}

	/**
	 * Get the value of the primary key/ID.
	 *
	 * @return mixed|null
	 */
	protected function getIdentifierValue()
	{
		return $this->{$this->identifierName};
	}

	/**
	 * Manually set the resource identifier on the model instance.
	 *
	 * This property is used to inform the model whether the object was retrieved via the API vs a manually hydrated
	 * object instance.
	 *
	 * @param mixed $value
	 * @return void
	 */
	public function setResourceIdentifier($value): void
	{
		$this->resourceIdentifier = $value;
	}

	/**
	 * Get the model's resource name (defaults to lowercase class name).
	 *
	 * @return string
	 */
	public function getResourceName(): string
	{
		if( empty($this->resourceName) ){

			$resourceName = \get_called_class();

			$pos = \strrpos($resourceName, "\\");

			if( $pos !== false ) {
				$resourceName = \substr($resourceName, $pos + 1);
			}

			$this->resourceName = \strtolower($resourceName);
		}

		return $this->resourceName;
	}

	/**
	 * Get the full resource URI of this instance.
	 *
	 * @return string
	 */
	protected function getResourceUri(): string
	{
		$uri = "";

		$dependencies = $this->getDependencies();

		if( $dependencies ){
			$uri .= "{$dependencies}/";
		}

		$uri .= $this->getResourceName();

		if( ($id = $this->getIdentifierValue()) ){
			$uri .= "/{$id}";
		}

		return $uri;
	}

	/**
	 * Save the resource.
	 *
	 * @param array<string,mixed> $queryParams
	 * @param array<string,mixed> $headers
	 * @return bool
	 */
	public function save(array $queryParams = [], array $headers = []): bool
	{
		// Get the connection to use for this Model
		$connection = ConnectionManager::get($this->connectionName);

		// By default, submit all data.
		$data = \array_merge($this->properties, $this->modifiedProperties);

		// No id, new (POST) resource instance.
		if( empty($this->getIdentifierValue()) ){
			$method = "post";
		}

		// Existing resource, update (PUT/POST/PATCH depending on API) resource instance
		else {

			// Can we just send the modified properties? (i.e. a PATCH)
			if( $connection->getOption(Connection::OPTION_UPDATE_DIFF) ){
				$data = $this->modifiedProperties;
			}

			// Get the update method (usually either PUT or PATCH)
			$method = $connection->getOption(Connection::OPTION_UPDATE_METHOD);
		}

		// Filter out excluded properties
		if( \is_array($this->excludedProperties) ){
			$data = \array_diff($data, $this->excludedProperties);
		}

		// Make request
		$response = $connection->send(
			$connection->buildRequest($method, $this->getResourceUri(), $queryParams, $this->serialize($data), $headers)
		);

		if( $connection->isResponseSuccessful($response) ){

			$this->hydrate(
				$this->parseFind(
					$this->deserialize($response->getBody()->getContents())
				)
			);

			$this->modifiedProperties = [];
			return true;
		}

		return false;
	}

	/**
	 * Destroy (delete) the resource.
	 *
	 * @param array<string,mixed> $queryParams
	 * @param array<string,mixed> $headers
	 * @return bool
	 */
	public function destroy(array $queryParams = [], array $headers = []): bool
	{
		$connection = ConnectionManager::get($this->connectionName);

		$connection->send(
			$connection->buildRequest("delete", $this->getResourceUri(), $queryParams, null, $headers)
		);

		return true;
	}

	/**
	 * Mass assign properties with an array of key/value pairs.
	 *
	 * @param array<string,mixed> $data
	 */
	public function fill(array $data): void
	{
		foreach( $data as $property => $value ){
			if( \is_array($this->fillableProperties) &&
				!\in_array($property, $this->fillableProperties) ){
				continue;
			}

			$this->{$property} = $value;
		}
	}

	/**
	 * Build a Collection of included resources in response payload.
	 *
	 * @param string $modelClass
	 * @param array $data
	 * @return array<Model>
	 */
	public function includesMany(string $modelClass, array $data)
	{
		return $this->buildCollection($modelClass, $data);
	}

	/**
	 * Build a single instance of an included resource in response payload.
	 *
	 * @param string $modelClass
	 * @param mixed $data
	 * @return Model
	 */
	public function includesOne(string $modelClass, $data): Model
	{
		if( empty($data) ||
			(!\is_object($data) && !\is_array($data)) ){
			return $data;
		}

		/**
		 * @var Model $modelInstance
		 */
		$modelInstance = new $modelClass;
		$modelInstance->hydrate((array) $data);
		return $modelInstance;
	}

	/**
	 * Set dependent resources to prepend to URI. You can call this method multiple times to prepend additional dependent
	 * resources.
	 *
	 * For example, if the API only allows you to create a new comment on a post *through* the post's URI:
	 *  POST /posts/1234/comment
	 *
	 * $comment = new Comment;
	 * $comment->through('posts/1234');
	 * $comment->body = "This is a comment";
	 * $comment->save();
	 *
	 * OR
	 *
	 * $post = Post::find(1234);
	 * $comment = new Comment;
	 * $comment->through($post);
	 * $comment->body = "This is a comment";
	 * $comment->save();
	 *
	 * @param Model|string $resource
	 * @return Model
	 */
	public function through($resource): Model
	{
		if( $resource instanceof static ){

			if( !\in_array($resource->getResourceUri(), $this->dependentResources) ){
				$this->dependentResources[] = $resource->getResourceUri();
			}
		}

		else {

			if( !\in_array($resource, $this->dependentResources) ){
				$this->dependentResources[] = $resource;
			}
		}

		return $this;
	}


	/**
	 * Get a model property.
	 *
	 * @param string $property
	 * @return mixed|null
	 */
	public function __get(string $property)
	{
		if( \array_key_exists($property, $this->modifiedProperties) ){
			return $this->modifiedProperties[$property];
		}
		elseif( \array_key_exists($property, $this->properties) ){
			return $this->properties[$property];
		}

		return null;
	}

	/**
	 * Set a model property.
	 *
	 * @param string $property
	 * @param mixed $value
	 */
	public function __set(string $property, $value): void
	{
		// Is this a read only property?
		if( isset($this->readOnlyProperties[$property]) ){
			return;
		}

		$this->modifiedProperties[$property] = $value;
	}

	/**
	 * Get the original value of a property (before it was modified).
	 *
	 * @param string $property
	 * @return mixed|null
	 */
	public function original(string $property)
	{
		if( \array_key_exists($property, $this->properties) ){
			return $this->properties[$property];
		}

		return null;
	}

	/**
	 * Reset all modified properties.
	 *
	 * @return void
	 */
	public function reset(): void
	{
		$this->modifiedProperties = [];
	}

	/**
	 * Reload instance from data source. Resets all modified properties.
	 *
	 * @param array $queryParams
	 * @param array $headers
	 * @return bool
	 */
	public function refresh(array $queryParams = [], array $headers = []): bool
	{
		$connection = ConnectionManager::get($this->connectionName);

		$response = $connection->send(
			$connection->buildRequest('get', $this->getResourceUri(), $queryParams, null, $headers)
		);

		if( $connection->isResponseSuccessful($response) ) {

			$this->properties = [];
			$this->modifiedProperties = [];
			$this->hydrate(
				$this->parseFind(
					$this->deserialize($response->getBody()->getContents())
				)
			);
			return true;

		}

		return false;
	}

	/**
	 * Convert the model into an array.
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		return self::objectToArray(
			\array_merge($this->properties, $this->modifiedProperties)
		);
	}

	/**
	 * Conver the model into JSON.
	 *
	 * @return string
	 */
	public function toJson(): string
	{
		return \json_encode(
			$this->toArray(),
			JSON_UNESCAPED_SLASHES
		);
	}

	/**
	 * Used to recursively convert model and relations into array.
	 *
	 * @param $data
	 * @return array
	 */
	private static function objectToArray($data): array
	{
		$result = [];

		foreach( $data as $property => $value )
		{
			if( $value instanceof Model )
			{
				$result[$property] = $value->toArray();
			}
			elseif( $value instanceof \StdClass )
			{
				$result[$property] = (array) $value;
			}
			elseif( is_array($value) ||
				$value instanceof \ArrayAccess )
			{
				$result[$property] = self::objectToArray($value);
			}
			else{
				$result[$property] = $value;
			}
		}

		return $result;
	}

	/**
	 * Default serialize method for Request payloads.
	 *
	 * You should override this method in your models (if necessary) or in a BaseModel class. This is also a good
	 * place to add any extra markup needed in the request body. For example:
	 *
	 * return json_encode(['data' => $data]);
	 *
	 * @param mixed $data
	 * @return string
	 */
	protected function serialize($data): string
	{
		return \json_encode($data);
	}

	/**
	 * Default deserialize method for Response payloads.
	 *
	 * You should override this method in your models or in a BaseModel class.
	 *
	 * @param string $data
	 * @return mixed
	 */
	protected function deserialize(string $data)
	{
		return \json_decode($data);
	}

	/**
	 * Get the Connection configured for the model.
	 *
	 * @throws ActiveResourceException
	 * @return Connection
	 */
	public function getConnection(): Connection
	{
		return ConnectionManager::get($this->connectionName);
	}

	/**
	 * Return an instance of the called class.
	 *
	 * @param array<string,mixed> $constructorData
	 * @return Model
	 */
	protected static function getModelInstance(array $constructorData = [])
	{
		$className = \get_called_class();
		return new $className($constructorData);
	}

	/**
	 * Is this entity modified?
	 *
	 * @return boolean
	 */
	protected function isModified(): bool
	{
		return \count($this->modifiedProperties) > 0;
	}

	/**
	 * Get any resource dependencies
	 *
	 * @return string
	 */
	protected function getDependencies(): string
	{
		return \implode("/", $this->dependentResources);
	}

	/**
	 * Where to find the single resource data from the response payload.
	 *
	 * You should overwrite this method in your model class to suit your needs.
	 *
	 * @param mixed $payload
	 * @return mixed
	 */
	protected function parseFind($payload)
	{
		return $payload;
	}

	/**
	 * Where to find the array of data from the response payload.
	 *
	 * You should overwrite this method in your model class to suit your needs.
	 *
	 * @param mixed $payload
	 * @return mixed
	 */
	protected function parseAll($payload)
	{
		return $payload;
	}

	/**
	 * Hydrate model instance with data.
	 *
	 * @param array<string, mixed> $data
	 * @return void
	 */
	protected function hydrate(array $data): void
	{
		foreach( $data as $property => $value ){

			if( method_exists($this, $property) ){
				$value = $this->{$property}($value);
			}

			$this->properties[$property] = $value;
		}
	}

	/**
	 * Find (GET) a specific resource by its ID
	 *
	 * This method assumes the payload contains a *SINGLE* resource instance. This method will call the
	 * parseFind method on the Model instance to know where to look in the payload to get the resource data.
	 *
	 * @param integer|string $id
	 * @param array $queryParams
	 * @param array $headers
	 * @throws ActiveResourceResponseException
	 * @return Model|null
	 */
	public static function find($id, array $queryParams = [], array $headers = []): ?Model
	{
		$instance = self::getModelInstance();

		$uri = $instance->getResourceUri() . "/{$id}";

		$connection = $instance->getConnection();

		// Build the request object
		$request = $connection->buildRequest("get", $uri, $queryParams, null, $headers);

		// Send the request
		$response = $connection->send($request);

		if( $connection->isResponseSuccessful($response) ) {
			$instance->hydrate(
				$instance->parseFind(
					$instance->deserialize($response->getBody()->getContents())
				)
			);
			return $instance;
		}

		return null;
	}

	/**
	 * Get ALL resources
	 *
	 * This method assumes the payload contains an ARRAY of resource instances. This method will call the
	 * parseAll method on the Model instance to know where to look in the payload to get the array of resource data.
	 *
	 * @param array<string,mixed> $queryParams
	 * @param array<string,mixed> $headers
	 * @throws ActiveResourceResponseException
	 * @return mixed
	 */
	public static function all(array $queryParams = [], array $headers = [])
	{
		$instance = self::getModelInstance();

		$connection = $instance->getConnection();

		// Build the request
		$request = $connection->buildRequest('get', $instance->getResourceUri(), $queryParams, null, $headers);

		// Send the request
		$response = $connection->send($request);

		if( $connection->isResponseSuccessful($response) ) {
			$data = $instance->parseAll($response->getBody()->getContents());
			return $instance->buildCollection(\get_called_class(), $data);
		}

		return null;
	}

	/**
	 * Delete a resource.
	 *
	 * @param mixed $id
	 * @param array<string,mixed> $queryParams
	 * @param array<string,mixed> $headers
	 * @throws ActiveResourceResponseException
	 * @return bool
	 */
	public static function delete($id, array $queryParams = [], array $headers = [])
	{
		$instance = self::getModelInstance();

		$uri = $instance->getResourceUri() . "/{$id}";

		$connection = $instance->getConnection();

		// Build request object
		$request = $connection->buildRequest('delete', $uri, $queryParams, null, $headers);

		// Send request
		$response = $connection->send($request);

		return $connection->isResponseSuccessful($response);
	}

	/**
	 * Find a single instance *through* a dependent resource. It prepends the resource URI with the given dependent
	 * resource URI. For example:
	 *  API URI: [GET] /posts/1234/comments/5678
	 *
	 *  $comment = Comment::findThrough('posts/1234', 5678);
	 *
	 *  OR
	 *
	 * $post = Post::find(1234);
	 * $comment = Comment::findThrough($post, 5678);
	 *
	 * @param Model|string $resource
	 * @param string|null $id
	 * @param array $queryParams
	 * @param array $headers
	 * @throws ActiveResourceResponseException
	 * @return Model|null
	 */
	public static function findThrough($resource, $id = null, array $queryParams = [], array $headers = []): ?Model
	{
		// Create model instance and set a dependent resource.
		$instance = self::getModelInstance()->through($resource);

		$connection = $instance->getConnection();

		// Send request
		$response = $connection->send(
			$connection->buildRequest("get", $instance->getResourceUri() . "/{$id}", $queryParams, null, $headers)
		);

		if( $connection->isResponseSuccessful($response) ) {
			$instance->hydrate(
				$instance->parseFind(
					$instance->deserialize($response->getBody()->getContents())
				)
			);

			return $instance;
		}

		return null;
	}

	/**
	 * Find all instances *through* a dependent resource. It prepends the resource URI with the given dependent
	 * resource URI. For example:
	 *
	 *  API URI: [GET] /posts/1234/comments
	 *
	 *  $comments = Comment::allThrough('posts/1234');
	 *
	 *  OR
	 *
	 * $post = Post::find(1234);
	 * $comments = Comment::allThrough($post);
	 *
	 * @param Model|string $resource
	 * @param array<string,mixed> $queryParams
	 * @param array<string,mixed> $headers
	 * @throws ActiveResourceResponseException
	 * @return mixed
	 */
	public static function allThrough($resource, array $queryParams = [], array $headers = [])
	{
		$instance = self::getModelInstance()->through($resource);

		$connection = $instance->getConnection();

		$response = $connection->send(
			$connection->buildRequest('get', $instance->getResourceUri(), $queryParams, null, $headers)
		);

		if( $connection->isResponseSuccessful($response) ) {
			return $instance->buildCollection(
				get_called_class(),
				$instance->parseAll(
					$instance->deserialize(
						$response->getBody()->getContents()
					)
				)
			);
		}

		return null;
	}

	/**
	 * Build a collection of models.
	 *
	 * @param string $modelClass
	 * @param array $data
	 * @return mixed
	 */
	protected function buildCollection(string $modelClass, array $data)
	{
		$instances = [];

		foreach( $data as $object ){
			/** @var Model $modelInstance */
			$modelInstance = new $modelClass;
			$modelInstance->hydrate($object);
			$instances[] = $modelInstance;
		}

		if( ($collectionClass = $this->getConnection()->getOption(Connection::OPTION_COLLECTION_CLASS)) ){
			return new $collectionClass($instances);
		}

		return $instances;
	}

	/**
	 * Get the Request object from the last call.
	 *
	 * @return RequestInterface
	 */
	public function getRequest(): RequestInterface
	{
		return $this->getConnection()->getLastRequest();
	}

	/**
	 * Get the Response object from the last call.
	 *
	 * @return ResponseInterface
	 */
	public function getResponse(): ResponseInterface
	{
		return $this->getConnection()->getLastResponse();
	}
}