# ActiveResource
PHP ActiveResource implementation. Use a RESTful resource based API like a database.

## Installation

    composer require nimbly/ActiveResource


## Configuration
ActiveResource lets you connect to any number of RESTful APIs within your code.

1. Create a `Connection` instance
2. Use `ConnectionManager::add` to assign a name and add the `Connection` to its pool of connections.

### Connection
Create a new `Connection` instance representing a connection to an API.

##### Options
The options array may contain:

`defaultUri` The default URI to prepend to each request. For example: `http://some.api.com/v2/`

`errorClass` Class name of the Error class to use for interacting with error responses from the API.

`responseClass` Class name of the Response class to use for parsing responses including headers and body.

`updateMethod` HTTP method to use for updates, defaults to `PUT`.

`middleware` An array of middleware classes to run before sending each request.

##### HttpClient
An optional instance of `GuzzleHttp\Client`. If you do not provide an instance, one will be created automatically
with no options set.

Example:

    $options = [
        'baseUri' => 'http://api.someurl.com/v1/',
        'updateMethod' => 'PATCH',
        'responseClass' => \My\Custom\Response::class,
        'errorClass' => \My\Custom\Error::class,
        'middleware' => [
            \My\Custom\Middleware\Authorize::class,
            \My\Custom\Middleware\Headers::class,
        ]
    ];
    
    $connection = new \ActiveResource\Connection($options);

### ConnectionManager

Use `ConnectionManager::add` to add one or more Connection instances. This allows you to use ActiveResource with any
number of APIs within your code.

Example:
    
    ConnectionManager::add('default', $connection);


## Response
Because each and every API responds with its own unique payload and encoding, you must
provide a response class that extends `\ActiveResource\ResponseAbstract`.

You must implement a `parse` method that returns an object or array representing the
response body and an `isSuccessful` method that returns a boolean value.
 
The Response object is also a great way to include any other methods to access non-payload
related data or headers. It all depends on what data is in the response body for the API you
are working with.

Example:
    
    class Response extends \ActiveResource\ResponseAbstract
    {
        public function parse($payload)
        {
            return json_decode($payload);
        }
        
        public function isSuccessful()
        {
            return $this->getStatusCode() < 400;
        }
        
        public function getMeta()
        {
            return $this->payload->meta;
        }
        
        public function getEnvelope()
        {
            return $this->pauload->envelope;
        }
    }
    
## Error
Because each API responds differently for its errors, you must implement an Error class
that extends `ErrorAbstract`. The `ErrorAbstract` class is instantiated with the parsed
response body (thanks to the Response class you created).

You must at a minimum implement the `getMessage` method.

The Error object is also a great way to include any other methods to access other properties
of the error response.

Example:

    class Error extends \ActiveResource\ErrorAbstract
    {
        public function getMessage()
        {
            return $this->payload->error->message;
        }
        
        /**
        *   Get the validation error field names
        */
        public function getFields()
        {
            return $this->payload->error->fields;
        }
    }

## Models

Create your model classes and extend them from `\ActiveResource\Model`.

##### Properties
`connectionName` Name of connection to use. Defaults to `default`.

`resourceName` Name of the API resource URI. Defaults to name of class.

`resourceIdentifier` Name of the field to use as the ID. Defaults to `id`.

##### Static methods
`find` Find a single instance of a resource. Assumes payload will return *single* object.

`all` Get all instances of a resource. Assumes payload will return an *array* of objects.

`remove` Delete a resource.

`findThrough` Find a resource *through* another resource. For example, if you have to retrieve
a comment through its post `/posts/1234/comments/5678`.

`allThrough` Get all instances of a resource *through* another resource. For example, if you have
to retrieve comments through its post `/posts/1235/comments`.

##### Instance methods
`fill` Mass assign object properties with an array of key/value pairs.

`save` Save or update the instance.

`delete` Delete the instance.

`getResponse` Get the `Response` object for the last request.

`getError` Get the `Error` object for the last request.

`parseFind` Tells the Model class where to look for the payload data for a single resource. Expects
single parameter containing the parsed/decoded payload.

`parseAll` Tells the Model class where to look for the payload data for an array of resources. Expects
single parameter containing the parsed/decoded payload.

`includesOne` Tells the Model class that the response includes a single instance of another
model class.

`includesMany` Tells the Model class that the response includes an array of instances of another
model class.

You can also define `public` methods with the same name as an instance property that the model will
use to modify the data.
 
Example:

    class Posts extends \ActiveResource\Model
    {
        protected $connectionName = 'sample-api';
        
        public function author($data)
        {
            return $this->includesOne(Users::class, $data);
        }
        
        public function comments($data)
        {
            return $this->includesMany(Comments::class, $data);
        }
        
        public function parseFind($payload)
        {
            return $payload->data->post;
        }
        
        public function parseAll($payload)
        {
            return $payload->data->posts;
        }
    }

## Middleware

You can modify and interact with the `Connection` object before it sends its request by using middleware.
A middleware class must implement a public `run` method and accepts the `Connection` instance
as its only parameter.

Keep in mind the connection request property is an instance of a Psr7\Request object which means
if you modify the request via any of its methods, it will return a *new* Psr7\Request instance.

Example:
    
    class Authorize
    {
        public function run(\ActiveResource\Connection $connection)
        {
            $connection->request = $connection->request->withHeader('X-Foo-Header', 'Bar');
        }
    }

## Examples

### Find a single resource
    $user = User::find($id);

### Get all resources
    $users = User::all();

### Creating a new resource
    $user = new User;
    $user->name = 'Test User';
    $user->email = 'test@example.com';
    $user->save();
    
### Updating a resource
    $user = User::find($id);
    $user->status = 'INACTIVE';
    $user->save();
    
### Delete a resource
    $user = User::find($id);
    $user->delete();

Or
    
    User::remove($id);
