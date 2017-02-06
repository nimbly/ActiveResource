<?php
/**
 * Created by PhpStorm.
 * User: brent
 * Date: 1/7/17
 * Time: 10:01 AM
 */

namespace ActiveResource;


abstract class Model
{
    /**
     * The unique key field for the resource
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


    private $resourceIdentifier = null;
    private $attributes = [];
    private $dirty = [];
    private $dependentResources = [];

    /** @var Request */
    private $request = null;

    /** @var ResponseAbstract */
    private $response = null;

    /** @var ErrorAbstract */
    private $error = null;

    public function __construct($data = null)
    {
        if( !empty($data) ){
            $this->hydrate($data);
        }
    }

    /**
     * Get the ID of the resource
     *
     * @return mixed|null
     */
    public function getId()
    {
        return $this->resourceIdentifier;
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
     * Get the Response object
     *
     * @return ResponseAbstract|null
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Get the error object
     *
     * @return ErrorAbstract|null
     */
    public function getError()
    {
        return $this->error;
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
        $data = array_merge($this->attributes, $this->dirty);

        // No id, new (POST) resource instance
        if( empty($this->resourceIdentifier) ){
            $method = 'post';
        }

        // Existing resource, update (PUT/PATCH) resource instance
        else {

            // Can we just send the dirty fields?
            if( $this->getConnection()->getOption(Connection::OPTION_UPDATE_DIFF) ){
                $data = $this->dirty;
            }

            // Get the update method (usually either PUT or PATCH)
            $method = $this->getConnection()->getOption(Connection::OPTION_UPDATE_METHOD);
        }

        // Build request object
        $request = $this->getConnection()->buildRequest($method, $this->getResourceUri(), $queryParams, $data, $headers);

        // Do the update
        $this->response = $this->getConnection()->send($request);
        if( $this->response->isSuccessful() ){
            $data = $this->parseFind($this->response->getPayload());
            $this->error = null;
            $this->hydrate($data);
            $this->dirty = [];
            return true;
        }

        // Set the error
        $this->error = $this->buildError($this->response);

        if( $this->response->isThrowable() ) {
            throw new ActiveResourceResponseException($this->error);
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
        $this->response = $this->getConnection()->send($request);
        if( $this->response->isSuccessful() ){
            $this->error = null;
            return true;
        }

        // Set the error
        $this->error = $this->buildError($this->response);

        // Throw if needed
        if( $this->response->isThrowable() ) {
            throw new ActiveResourceResponseException($this->error);
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
        foreach( $data as $key => $value ){
            $this->{$key} = $value;
        }
    }

    /**
     * Build a Collection of included resources in response payload.
     *
     * @param string $model
     * @param array $data
     *
     * @return Collection
     */
    public function includesMany($model, array $data)
    {
        if( empty($data) ){
            return new Collection($model, []);
        }

        return new Collection($model, $data);
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

        return new $model($data);
    }

    /**
     * Set dependent resources to prepend to URI. You can call this method multiple times to prepend additional dependent
     * resources.
     *
     * For example, if the API only allows you create a new comment on a post *through* the post's URI:
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
     */
    public function through($resource)
    {
        if( $resource instanceof Model ){
            $this->dependentResources[] = $resource->getResourceUri();
        }

        else{
            $this->dependentResources[] = $resource;
        }
    }


    /**
     * Magic getter
     *
     * @param $property
     * @return mixed|null
     */
    public function __get($property)
    {
        if( array_key_exists($property, $this->dirty) ){
            return $this->dirty[$property];
        }

        elseif( array_key_exists($property, $this->attributes) ){
            return $this->attributes[$property];
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
        if( $property == $this->identifierName ){
            $this->resourceIdentifier = $value;
        }

        $this->dirty[$property] = $value;
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
        if( array_key_exists($property, $this->attributes) ){
            return $this->attributes[$property];
        }

        return null;
    }

    /**
     * Reset all modified properties, reset response, reset error
     *
     * @return void
     */
    public function reset()
    {
        $this->dirty = [];
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $attributes = array_merge($this->attributes, $this->dirty);

        foreach( $attributes as $property => $value )
        {
            if( $value instanceof Model )
            {
                $attributes[$property] = $value->toArray();
            }
            elseif( $value instanceof \StdClass )
            {
                $attributes[$property] = (array)$value;
            }
            else{
                $attributes[$property] = $value;
            }
        }

        return $attributes;
    }

    /**
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }

    /**
     * Get the model's resource name (defaults to class name)
     *
     * @return null|string
     */
    public function getResourceName()
    {
        if( empty($this->resourceName) ){

            $class = get_called_class();

            if( ($pos = strrpos($class, '\\')) ) {
                $resourceName = substr($class, $pos + 1);
            }

            else {
                $resourceName = $class;
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
     * @param null $constrcutorData
     * @return self
     */
    protected static function getCalledClassInstance($constrcutorData = null)
    {
        $className = get_called_class();
        return new $className($constrcutorData);
    }


    /**
     * Is this entity dirty?
     *
     * @return int
     */
    protected function isDirty()
    {
        return count($this->dirty);
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
     * Hydrate
     *
     * @param $data
     *
     * @throws ActiveResourceException
     *
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
            foreach( get_object_vars($data) as $key => $value ){

                if( $key == $this->identifierName ){
                    $this->resourceIdentifier = $value;
                }

                // is there some sort of filter method on this property?
                if( method_exists($this, $key) ){
                    $this->attributes[$key] = $this->{$key}($value);
                }

                else {
                    $this->attributes[$key] = $value;
                }
            }

            return true;
        }

        throw new ActiveResourceException('Failed to hydrate. Invalid payload data format.');
    }


    /**
     * Find (GET) a specific resource by its ID (optional)
     *
     * This method assumes the payload contains a *SINGLE* resource instance. This method will call the
     * parseFind method on the Model instance to know where to look in the payload to get the resource data.
     *
     * @param integer|string|null $id
     * @param array $queryParams
     * @param array $headers
     *
     * @throws ActiveResourceResponseException
     *
     * @return Model|boolean
     */
    public static function find($id = null, array $queryParams = [], array $headers = [])
    {
        $instance = self::getCalledClassInstance();
        $instance->{$instance->getIdentifierName()} = $id;

        // Build the request object
        $request = $instance->getConnection()->buildRequest('get', $instance->getResourceUri(), $queryParams, null, $headers);

        // Send the
        $response = $instance->getConnection()->send($request);
        if( $response->isSuccessful() ) {
            $instance->response = $response;
            $data = $instance->parseFind($response->getPayload());
            $instance->hydrate($data);
            return $instance;
        }

        if( $response->isThrowable() ) {
            throw new ActiveResourceResponseException($instance->buildError($response));
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
     * @return Collection|boolean
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
            return new Collection(get_called_class(), $data, $response);
        }

        if( $response->isThrowable() ) {
            throw new ActiveResourceResponseException($instance->buildError($response));
        }

        return false;
    }

    /**
     * Delete a resource
     *
     * @param $id
     * @param $options
     *
     * @throws ActiveResourceResponseException
     *
     * @return bool
     */
    public static function delete($id, array $queryParams = [], array $headers)
    {
        $instance = self::getCalledClassInstance();
        $instance->{$instance->getIdentifierName()} = $id;

        // Build request object
        $request = $instance->getConnection()->buildRequest('delete', $instance->getResourceUri(), $queryParams, null, $headers);

        // Send request
        $response = $instance->getConnection()->send($request);
        if( $response->isSuccessful() ) {
            return true;
        }

        if( $response->isThrowable() ) {
            throw new ActiveResourceResponseException($instance->buildError($response));
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
        $instance->{$instance->getIdentifierName()} = $id;
        $instance->through($resource);

        // Build request object
        $request = $instance->getConnection()->buildRequest('get', $instance->getResourceUri(), $queryParams, null, $headers);

        // Do request
        $response = $instance->getConnection()->send($request);

        if( $response->isSuccessful() ) {
            $instance->response = $response;
            $data = $instance->parseFind($response->getPayload());
            $instance->hydrate($data);
            return $instance;
        }

        if( $response->isThrowable() ) {
            throw new ActiveResourceResponseException($instance->buildError($response));
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
            return new Collection(get_called_class(), $data, $response);
        }

        if( $response->isThrowable() ) {
            throw new ActiveResourceResponseException($instance->buildError($response));
        }

        return false;
    }

    /**
     * Build an instance of an Error response object
     *
     * @param ResponseAbstract $response
     * @return ErrorAbstract
     */
    protected function buildError(ResponseAbstract $response)
    {
        $errorClass = $this->getConnection()->getOption(Connection::OPTION_ERROR_CLASS);
        return new $errorClass($response);
    }

    /**
     * Send a custom request
     *
     * @param string $method
     * @param string $uri
     * @param array $queryParams
     * @param array $headers
     * @param string|array|null $body
     * @return ResponseAbstract
     */
    public static function send($method, $uri, array $queryParams = [], array $headers = [], $body = null)
    {
        $instance = self::getCalledClassInstance();
        $request = $instance->getConnection()->buildRequest($method, $uri, $queryParams, $body, $headers);

        return $instance->getConnection()->send($request);
    }

    /**
     * Get the API connection
     *
     * @return Connection
     */
    public static function connection(){
        return self::getCalledClassInstance()->getConnection();
    }
}