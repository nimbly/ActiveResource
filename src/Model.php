<?php

namespace ActiveResource;


abstract class Model
{
	/**
	 * The unique/primary key field for the resource
	 *
	 * @var string $identifierName
	 */
	protected $identifierName = 'id';

	/**
	 * The name of the resource URI - defaults to the lowercase name of the Model class
	 *
	 * @var string $resourceName
	 */
	protected $resourceName = null;

	/**
	 * The connection name to use for this resource
	 *
	 * @var string $connectionName
	 */
	protected $connectionName = 'default';

	/**
	 * Array of property names that are read only. I.e. when setting named property, it will not modify.
	 *
	 * When set to null or empty array, all properties are writeable.
	 *
	 * @var null|array
	 */
	protected $readOnlyProperties = null;

	/**
	 * When set to array of property names, only these properties are allowed to be mass assigned when calling the fill() method.
	 *
	 * If null, *all* properties can be mass assigned.
	 *
	 * @var null|array
	 */
	protected $fillableProperties = null;

	/**
	 * Array of property names that are excluded when saving/updating model to API.
	 *
	 * If null or empty array, all properties will be sent when saving model.
	 *
	 * @var null|array
	 */
	protected $excludedProperties = null;

	/** @var string|integer|null $resourceIdentifier */
	private $resourceIdentifier = null;

	/** @var array $properties */
	private $properties = [];

	/** @var array $modifiedProperties */
	private $modifiedProperties = [];

	/** @var array $dependentResources */
	private $dependentResources = [];

	/**
	 * Model constructor.
	 * @param array|object|null $data
	 */
	public function __construct($data = null)
	{
		if( !empty($data) ){
			$this->fill($data);
		}
	}

	/**
	 * Get the ID of the resource
	 *
	 * @return mixed|null
	 */
	public function getId()
	{
		return $this->{$this->identifierName};
	}

	/**
	 * Get the identifier property name (defaults to "id")
	 *
	 * @return string
	 */
	public function getIdentifierName()
	{
		return $this->identifierName;
	}

	/**
	 * Get the full resource URI
	 *
	 * @return string
	 */
	public function getResourceUri()
	{
		$uri = '';

		if( ($dependencies = $this->getDependencies()) ){
		$uri.="{$dependencies}/";
		}

		$uri.=$this->getResourceName();

		if( ($id = $this->getId()) ){
			$uri.="/{$id}";
		}

		return $uri;
	}

	/**
	 * Save the entity
	 *
	 * @param array $queryParams
	 * @param array $headers
	 *
	 * @return bool
	 */
	public function save(array $queryParams = [], array $headers = [])
	{
		// By default, submit all data
		$data = array_merge($this->properties, $this->modifiedProperties);

		// No id, new (POST) resource instance
		if( $this->{$this->identifierName} == false ){
			$method = 'post';
		}

		// Existing resource, update (PUT/POST/PATCH depending on API) resource instance
		else {

			// Can we just send the modified properties? (i.e. a PATCH)
			if( $this->getConnection()->getOption(Connection::OPTION_UPDATE_DIFF) ){
				$data = $this->modifiedProperties;
			}

			// Get the update method (usually either PUT or PATCH)
			$method = $this->getConnection()->getOption(Connection::OPTION_UPDATE_METHOD);
		}

		// Filter out excluded properties
		if( is_array($this->excludedProperties) ){
			$data = array_diff($data, $this->excludedProperties);
		}

		// Build request object
		$request = $this->getConnection()->buildRequest($method, $this->getResourceUri(), $queryParams, $this->encode($data), $headers);

		// Do the update
		$response = $this->getConnection()->send($request);
		if( $response->isSuccessful() ){
			$this->hydrate($this->parseFind($response->getPayload()));
			$this->modifiedProperties = [];
			return true;
		}

		// Should we throw an exception?
		if( $response->isThrowable() ) {
			throw new ActiveResourceResponseException($response);
		}

		return false;
	}

	/**
	 * Destroy (delete) the resource
	 *
	 * @param array $queryParams
	 * @param array $headers
	 *
	 * @return bool
	 */
	public function destroy(array $queryParams = [], array $headers = [])
	{
		// Build request
		$request = $this->getConnection()->buildRequest('delete', $this->getResourceUri(), $queryParams, null, $headers);

		// Get response
		$response = $this->getConnection()->send($request);
		if( $response->isSuccessful() ){
			return true;
		}

		// Throw if needed
		if( $response->isThrowable() ) {
			throw new ActiveResourceResponseException($response);
		}

		return false;
	}


	/**
	 * Mass assign properties with an array of key/value pairs
	 *
	 * @param array $data
	 */
	public function fill(array $data)
	{
		foreach( $data as $property => $value ){
			if( is_array($this->fillableProperties) &&
				!in_array($property, $this->fillableProperties) ){
				continue;
			}

			$this->{$property} = $value;
		}
	}

	/**
	 * Build a Collection of included resources in response payload.
	 *
	 * @param string $model
	 * @param array $data
	 *
	 * @return array|mixed
	 */
	public function includesMany($model, array $data)
	{
		if( empty($data) ){
			return $this->buildCollection($model, []);
		}

		return $this->buildCollection($model, $data);
	}

	/**
	 * Build a single instance of an included resource in response payload.
	 *
	 * @param string $model
	 * @param $data
	 * @return Model
	 */
	public function includesOne($model, $data)
	{
		if( empty($data) ||
			(!is_object($data) && !is_array($data)) ){
			return $data;
		}

		/** @var Model $modelInstance */
		$modelInstance = new $model;
		$modelInstance->hydrate($data);

		/** @var self $instance */
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
	 *
	 * @return Model
	 */
	public function through($resource)
	{
		if( $resource instanceof Model ){

			if( !in_array($resource->getResourceUri(), $this->dependentResources) ){
				$this->dependentResources[] = $resource->getResourceUri();
			}

		}

		else{

			if( !in_array($resource, $this->dependentResources) ){
				$this->dependentResources[] = $resource;
			}

		}

		return $this;
	}


	/**
	 * Magic getter
	 *
	 * @param $property
	 * @return mixed|null
	 */
	public function __get($property)
	{
		if( array_key_exists($property, $this->modifiedProperties) ){
			return $this->modifiedProperties[$property];
		}

		elseif( array_key_exists($property, $this->properties) ){
			return $this->properties[$property];
		}

		return null;
	}

	/**
	 * Magic setter
	 *
	 * @param $property
	 * @param $value
	 */
	public function __set($property, $value)
	{
		// Is this a read only property?
		if( is_array($this->readOnlyProperties) &&
			in_array($property, $this->readOnlyProperties) ){
			return;
		}

		$this->modifiedProperties[$property] = $value;
	}

	/**
	 * Get the original value of a property (before it was modified).
	 *
	 *
	 * @param $property
	 * @return mixed|null
	 */
	public function original($property)
	{
		if( array_key_exists($property, $this->properties) ){
			return $this->properties[$property];
		}

		return null;
	}

	/**
	 * Reset all modified properties
	 *
	 * @return void
	 */
	public function reset()
	{
		$this->modifiedProperties = [];
	}

	/**
	 * Reset all data on model instance and reload from remote API.
	 *
	 * @param array $queryParams
	 * @param array $headers
	 * @return bool
	 */
	public function refresh(array $queryParams = [], array $headers = [])
	{
		// Build the request object
		$request = $this->getConnection()->buildRequest('get', $this->getResourceUri(), $queryParams, null, $headers);

		// Send the request
		$response = $this->getConnection()->send($request);
		if( $response->isSuccessful() ) {

			// Clear out all local properties and modified properties
			$this->properties = [];
			$this->modifiedProperties = [];

			$this->hydrate($this->parseFind($response->getPayload()));
			return true;
		}

		if( $response->isThrowable() ) {
			throw new ActiveResourceResponseException($response);
		}

		return false;
	}

	/**
	 * @return array
	 */
	public function toArray()
	{
		$properties = array_merge($this->properties, $this->modifiedProperties);
		return self::objectToArray($properties);
	}

	/**
	 * @return string
	 */
	public function toJson()
	{
		return json_encode($this->toArray(), JSON_UNESCAPED_SLASHES);
	}

	/**
	 * Used to recursively convert model and relations into array
	 *
	 * @param $data
	 * @return array
	 */
	private static function objectToArray($data)
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
				$result[$property] = (array)$value;
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
	 * Default encode method for Request payloads. Need to send JSON or XML or something else?
	 *
	 * Gets called for all PUT/POST/PATCH calls to the API.
	 *
	 * You should override this method in your models (if necessary) or in a BaseModel class. This is also a good
	 * place to add any extra markup needed in the request body. For example:
	 *
	 * return json_encode(['data' => $data]);
	 *
	 * @param string
	 *
	 * @return string
	 */
	protected function encode($data)
	{
		return json_encode($data);
	}

	/**
	 * Get the model's resource name (defaults to lowercase class name)
	 *
	 * @return null|string
	 */
	public function getResourceName()
	{
		if( empty($this->resourceName) ){

			$resourceName = get_called_class();

			if( ($pos = strrpos($resourceName, '\\')) !== false ) {
				$resourceName = substr($resourceName, $pos + 1);
			}

			$this->resourceName = strtolower($resourceName);
		}

		return $this->resourceName;
	}

	/**
	 * Get the API connection for the model
	 *
	 * @return Connection
	 */
	public function getConnection()
	{
		return ConnectionManager::get($this->connectionName);
	}

	/**
	 * Return an instance of the called class
	 *
	 * @param null $constructorData
	 * @return self
	 */
	protected static function getCalledClassInstance($constructorData = null)
	{
		$className = get_called_class();
		return new $className($constructorData);
	}

	/**
	 * Is this entity modified?
	 *
	 * @return int
	 */
	protected function isModified()
	{
		return count($this->modifiedProperties) > 0;
	}

	/**
	 * Get any resource dependencies
	 *
	 * @return string
	 */
	protected function getDependencies()
	{
		return implode('/', $this->dependentResources);
	}

	/**
	 * Where to find the single resource data from the response payload.
	 *
	 * You should overwrite this method in your model class to suite your needs.
	 *
	 * @param $payload
	 * @return mixed
	 */
	protected function parseFind($payload)
	{
		return $payload;
	}

	/**
	 * Where to find the array of data from the response payload.
	 *
	 * You should overwrite this method in your model class to suite your needs.
	 *
	 * @param $payload
	 * @return mixed
	 */
	protected function parseAll($payload)
	{
		return $payload;
	}

	/**
	 * Hydrate model instance
	 *
	 * @param array|object $data
	 * @throws ActiveResourceException
	 * @return boolean
	 */
	protected function hydrate($data)
	{
		if( empty($data) ){
			return true;
		}

		// Convert array based data into object
		if( is_array($data) ) {
			$data = (object)$data;
		}

		// Process the data payload object
		if( is_object($data) ){
			foreach( get_object_vars($data) as $property => $value ){

				// is there some sort of filter method on this property?
				if( method_exists($this, $property) ){
					$value = $this->{$property}($value);
				}

				$this->properties[$property] = $value;
			}

			return true;
		}

		throw new ActiveResourceException('Failed to hydrate - invalid data format.');
	}

	/**
	 * Manually set the resource identifier on the model instance.
	 *
	 * This property is used to inform the model whether the object was retrieved via the API vs a manually hydrated
	 * object instance.
	 *
	 * @param $value
	 */
	public function setResourceIdentifier($value)
	{
		$this->resourceIdentifier = $value;
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
	 *
	 * @throws ActiveResourceResponseException
	 *
	 * @return Model|boolean
	 */
	public static function find($id, array $queryParams = [], array $headers = [])
	{
		$instance = self::getCalledClassInstance();

		$uri = $instance->getResourceUri() . "/{$id}";

		// Build the request object
		$request = $instance->getConnection()->buildRequest('get', $uri, $queryParams, null, $headers);

		// Send the request
		$response = $instance->getConnection()->send($request);
		if( $response->isSuccessful() ) {
			$instance->hydrate($instance->parseFind($response->getPayload()));
			return $instance;
		}

		if( $response->isThrowable() ) {
			throw new ActiveResourceResponseException($response);
		}

		return false;
	}

	/**
	 * Get ALL resources
	 *
	 * This method assumes the payload contains an ARRAY of resource instances. This method will call the
	 * parseAll method on the Model instance to know where to look in the payload to get the array of resource data.
	 *
	 * @param array $queryParams
	 * @param array $headers
	 *
	 * @throws ActiveResourceResponseException
	 *
	 * @return array|boolean|mixed
	 */
	public static function all(array $queryParams = [], array $headers = [])
	{
		$instance = self::getCalledClassInstance();

		// Build the request
		$request = $instance->getConnection()->buildRequest('get', $instance->getResourceUri(), $queryParams, null, $headers);

		// Send the request
		$response = $instance->getConnection()->send($request);
		if( $response->isSuccessful() ) {
			$data = $instance->parseAll($response->getPayload());
			return $instance->buildCollection(get_called_class(), $data);
		}

		if( $response->isThrowable() ) {
			throw new ActiveResourceResponseException($response);
		}

		return false;
	}

	/**
	 * Delete a resource
	 *
	 * @param mixed $id
	 * @param array<string,mixed> $queryParams
	 * @param array<string,mixed> $headers
	 * @throws ActiveResourceResponseException
	 * @return bool
	 */
	public static function delete($id, array $queryParams = [], array $headers = [])
	{
		$instance = self::getCalledClassInstance();

		$uri = $instance->getResourceUri() . "/{$id}";

		// Build request object
		$request = $instance->getConnection()->buildRequest('delete', $uri, $queryParams, null, $headers);

		// Send request
		$response = $instance->getConnection()->send($request);
		if( $response->isSuccessful() ) {
			return true;
		}

		if( $response->isThrowable() ) {
			throw new ActiveResourceResponseException($response);
		}

		return false;
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
	 *
	 * @throws ActiveResourceResponseException
	 *
	 * @return Model|bool
	 */
	public static function findThrough($resource, $id = null, array $queryParams = [], array $headers = [])
	{
		$instance = self::getCalledClassInstance();
		$instance->through($resource);
		$uri = $instance->getResourceUri() . "/{$id}";

		// Build request object
		$request = $instance->getConnection()->buildRequest('get', $uri, $queryParams, null, $headers);

		// Do request
		$response = $instance->getConnection()->send($request);
		if( $response->isSuccessful() ) {
			$instance->hydrate($instance->parseFind($response->getPayload()));
			return $instance;
		}

		if( $response->isThrowable() ) {
			throw new ActiveResourceResponseException($response);
		}

		return false;
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
	 * @param array $queryParams
	 * @param array $headers
	 *
	 * @throws ActiveResourceResponseException
	 *
	 * @return Collection|bool
	 */
	public static function allThrough($resource, array $queryParams = [], array $headers = [])
	{
		$instance = self::getCalledClassInstance();
		$instance->through($resource);

		// Build request object
		$request = $instance->getConnection()->buildRequest('get', $instance->getResourceUri(), $queryParams, null, $headers);

		// Do request, get response
		$response = $instance->getConnection()->send($request);
		if( $response->isSuccessful() ) {
			$data = $instance->parseAll($response->getPayload());
			return $instance->buildCollection(get_called_class(), $data);
		}

		if( $response->isThrowable() ) {
			throw new ActiveResourceResponseException($response);
		}

		return false;
	}

	/**
	 * Build a collection of models
	 *
	 * @param string $model
	 * @param array $data
	 *
	 * @return array|mixed
	 */
	protected function buildCollection($model, array $data)
	{
		$instances = [];
		foreach( $data as $object ){
			/** @var Model $modelInstance */
			$modelInstance = new $model;
			$modelInstance->hydrate($object);
			$instances[] = $modelInstance;
		}

		if( ($collectionClass = $this->getConnection()->getOption(Connection::OPTION_COLLECTION_CLASS)) ){
			return new $collectionClass($instances);
		}

		return $instances;
	}

	/**
	 * Get the Request object from the last API call
	 *
	 * @return Request
	 */
	public function getRequest()
	{
		return $this->getConnection()->getLastRequest();
	}

	/**
	 * Get the Response object from the last API call
	 *
	 * @return ResponseAbstract
	 */
	public function getResponse()
	{
		return $this->getConnection()->getLastResponse();
	}
}