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
     * Get the full resource URI
     *
     * @return string
     */
    public function getResourceUri()
    {
        $uri = '';

        if( ($dependencies = $this->getDependencies()) ){
           $uri.=$this->getDependencies();
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
     * @return ErrorAbstract
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
        $connection = $this->getConnection();
        $data = array_merge($this->attributes, $this->dirty);

        // No id, new (POST) resource instance
        if( empty($this->resourceIdentifier) ){
            $this->response = $connection->post($this->getResourceUri(), $queryParams, $data, $headers);
        }

        // Existing resource, update (PUT/PATCH) resource instance
        else {
            // if there's nothing to update, don't send the request
            if( $this->isDirty() == false ){
                return true;
            }

            // Can we just send the dirty fields?
            if( $this->getConnection()->getOption(Connection::OPTION_UPDATE_DIFF) ){
                $data = $this->dirty;
            }

            // Get the update method (usually either PUT or PATCH)
            $method = $connection->getUpdateMethod();

            // Do the update
            $this->response = $connection->{$method}($this->getResourceUri(), $queryParams, $data, $headers);
        }

        // Looks like a good response, re-hydrate object, and reset the dirty fields
        if( $this->response->isSuccessful() ){
            $data = $this->parseFind($this->response->getPayload());
            $this->error = null;
            $this->hydrate($data);
            $this->dirty = [];
            return true;
        }

        // Set the error
        $errorClass = $connection->getErrorClass();
        $this->error = new $errorClass($this->response);

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
        $connection = $this->getConnection();

        $this->response = $connection->delete($this->getResourceUri(), $queryParams, $headers);

        if( $this->response->isSuccessful() ){
            $this->error = null;
            return true;
        }

        // Set the error
        $errorClass = $connection->getErrorClass();
        $this->error = new $errorClass($this->response);

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
     * Get the original value of a property
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
     * Get the connection for the model
     *
     * @return Connection
     */
    protected function getConnection()
    {
        return ConnectionManager::get($this->connectionName);
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

                // is there some sort of filter on this property?
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
        $className = get_called_class();

        /** @var self $instance */
        $instance = new $className;

        $uri = $instance->getResourceUri();

        if( $id ){
            $uri.="/{$id}";
        }

        $response = $instance->getConnection()->get($uri, $queryParams, $headers);

        if( $response->isSuccessful() ) {
            $instance->response = $response;
            $data = $instance->parseFind($response->getPayload());
            $instance->hydrate($data);
            return $instance;
        }

        if( $response->isThrowable() ) {
            $errorClass = $instance->getConnection()->getErrorClass();
            $error = new $errorClass($response);

            throw new ActiveResourceResponseException($error);
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
        $className = get_called_class();

        /** @var self $instance */
        $instance = new $className;

        $response = $instance->getConnection()->get($instance->getResourceName(), $queryParams, $headers);

        if( $response->isSuccessful() ) {
            $instance->response = $response;
            if( method_exists($instance, 'parseAll') ){
                $data = $instance->parseAll($response->getPayload());
            }
            else {
                $data = $response->getPayload();
            }

            return new Collection($className, $data);
        }

        if( $response->isThrowable() ) {
            $errorClass = $instance->getConnection()->getErrorClass();
            $error = new $errorClass($response);

            throw new ActiveResourceResponseException($error);
        }

        return false;
    }

    /**
     * Delete a resource
     *
     * @param $id
     * @param array $queryParams
     * @param array $headers
     *
     * @throws ActiveResourceResponseException
     *
     * @return bool
     */
    public static function delete($id, array $queryParams = [], array $headers = [])
    {
        $className = get_called_class();

        /** @var self $instance */
        $instance = new $className;
        $instance->id = $id;

        $response = $instance->getConnection()->delete($instance->getResourceUri(), $queryParams, $headers);

        if( $response->isSuccessful() ) {
            $instance->response = $response;
            if( method_exists($instance, 'parseAll') ){
                $data = $instance->parseAll($response->getPayload());
            }
            else {
                $data = $response->getPayload();
            }

            return $instance->hydrate($data);
        }

        if( $response->isThrowable() ) {
            $errorClass = $instance->getConnection()->getErrorClass();
            $error = new $errorClass($response);

            throw new ActiveResourceResponseException($error);
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
     * @param $id
     * @param array $queryParams
     * @param array $headers
     *
     * @throws ActiveResourceResponseException
     *
     * @return Model|bool
     */
    public static function findThrough($resource, $id, array $queryParams = [], array $headers = [])
    {
        $className = get_called_class();

        /** @var self $instance */
        $instance = new $className;

        /** @var Model $resource */
        if( $resource instanceof Model )
        {
            $uri = "{$resource->getResourceUri()}/{$instance->getResourceUri()}/{$id}";
        }

        else {
            $uri = "{$resource}/{$instance->getResourceUri()}/{$id}";
        }

        $response = $instance->getConnection()->get($uri, $queryParams, $headers);

        if( $response->isSuccessful() ) {
            $instance->response = $response;
            $data = $instance->parseFind($response->getPayload());
            $instance->hydrate($data);
            return $instance;
        }

        if( $response->isThrowable() ) {
            $errorClass = $instance->getConnection()->getErrorClass();
            $error = new $errorClass($response);

            throw new ActiveResourceResponseException($error);
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
        $className = get_called_class();

        /** @var self $instance */
        $instance = new $className;

        /** @var Model $resource */
        if( $resource instanceof Model )
        {
            $uri = "{$resource->getResourceUri()}/{$instance->getResourceUri()}";
        }

        else {
            $uri = "{$resource}/{$instance->getResourceUri()}";
        }

        $response = $instance->getConnection()->get($uri, $queryParams, $headers);

        if( $response->isSuccessful() ) {
            $instance->response = $response;
            if( method_exists($instance, 'parseAll') ){
                $data = $instance->parseAll($response->getPayload());
            }
            else {
                $data = $response->getPayload();
            }

            return new Collection($className, $data);
        }

        if( $response->isThrowable() ) {
            $errorClass = $instance->getConnection()->getErrorClass();
            $error = new $errorClass($response);

            throw new ActiveResourceResponseException($error);
        }

        return false;
    }

    /**
     * Get the named Connection instance
     *
     * @param $connectionName
     *
     * @throws ActiveResourceException
     *
     * @return Connection
     */
    public static function connection($connectionName){
        return ConnectionManager::get($connectionName);
    }
}