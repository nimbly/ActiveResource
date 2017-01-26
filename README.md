# ActiveResource
Framework agnostic PHP ActiveResource implementation.

Use a RESTful resource based API like a database in an ActiveRecord pattern.

## Author's note
This project was started because I could not seem to find a good, maintained, and easy to use PHP based
ActiveResource package. Even though it's still in its infancy, I have personally used it on two
separate projects interacting with two completely different APIs: one built and maintained by me and
the other a 3rd party.

I hope you will find it as useful as I have.

If you have any suggestions or potential feature requests, feel free to ping me.

## Installation

    composer require nimbly/activeresource


## Configuration
ActiveResource lets you connect to any number of RESTful APIs within your code.

1. Create a `Connection` instance
2. Use `ConnectionManager::add` to assign a name and add the `Connection` to its pool of connections.

### Connection
Create a new `Connection` instance representing a connection to an API. The constructor takes two parameters:

##### Options
The options array may contain:

`defaultUri` *string* The default URI to prepend to each request. For example: `http://some.api.com/v2/`

`defaultHeaders` *array* Key => value pairs of headers to include with every request.

`defaultQueryParams` *array* Key => value pairs to include in the query with every request.

`errorClass` *string* Class name of the Error class to use for interacting with error responses from the API. Default
is ActiveResource's default Error class. See Error section for more info.

`responseClass` *string* Class name of the Response class to use for parsing responses including headers and body. Default
is ActiveResource's default Response class. SeeResponse section for more info.

`updateMethod` *string* HTTP method to use for updates. Defaults to `put`.

`updateDiff` *boolean* Whether ActiveResource can send just the modified fields of the resource on an update.

`middleware` *array* An array of middleware classes to run before sending each request. See Middleware section for more
info.

`log` *boolean* Tell ActiveResource to log all requests and responses. Defaults to `false`. Do not use this option
in production environments. You can access the log via the Connection getLog() method via the ConnectionManager.

##### HttpClient
An optional instance of `GuzzleHttp\Client`. If you do not provide an instance, one will be created automatically
with no options set.

###### Example

    $options = [
        'baseUri' => 'http://api.someurl.com/v1/',
        'updateMethod' => 'patch',
        'updateDiff' => true,
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

###### Example
    
    ConnectionManager::add('default', $connection);


## Response
Although ActiveResource comes with a basic Response class (that simply JSON decodes the response body), each and every
API responds with its own unique payload and encoding and it is recommended you provide your own custom response class that
extends `\ActiveResource\ResponseAbstract`.

### Required method implementation
`parse` Accepts the raw payload contents from the response. Should return an array or \StdClass
object representing the data. See Expected Data Format for more details.

`isSuccessful` Should return a boolean indicating whether the request was successful or not. Some APIs
do not adhere to strict REST patterns and may return an HTTP Status Code of 200 for all requests. In this
case there is usually a property in the payload indicating whether the request was successful or not.
 
The Response object is also a great way to include any other methods to access non-payload
related data or headers. It all depends on what data is in the response body for the API you
are working with.

###### Example
    
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
            return $this->getPayload()->meta;
        }
        
        public function getEnvelope()
        {
            return $this->getPayload()->envelope;
        }
    }
    

## Expected data format
In order for ActiveResource to properly hydrate your Model instances, the parsed payload data must be
 formatted in the following pattern:
 
     {
         property1: "value",
         property2: "value",
         property3: "value",
         related_single_resource: {
             property1: "value",
             property2: "value",
         },
         related_multiple_resources: [
             {
                 property1: "value",
                 property2: "value"
             }
         ]
     }

###### Example

    {
        "id": "1234",
        "title": "Blog post",
        "body": "This is a blog post",
        "author": {
            "id": "32135",
            "name": "John Doe",
            "email": "jdoe@example.com",
        },
        "comments": [
            {
                "id": "18319",
                "body": "This is a comment",
                "author": {
                    "id": "49913",
                    "name": "Jane Doe",
                    "email": "jane.doe@example.com"
                }
            },
                
            {
                "id": "18320",
                "body": "This is another comment",
                "author": {
                    "id": "823194",
                    "name": "Thomas Quigley",
                    "email": "tquigley@example.com"
                }
            }
        ]
    }    

If the API you are working with does not have its data formatted in this manor - you will need to transform it so that it is.
This can (and should) be done in your `Response` class `parse` method.

    
## Error
Although ActiveResource comes with a basic Error class, each API responds differently for its errors and it is
highly recommended to implement your own Error class that extends `ErrorAbstract`. The `ErrorAbstract` class is
instantiated with the Response instance.

The Error object is also a great way to include any other methods to access other properties
of the error response.

#### Required method implementation
`getMessage` Should return the error message from the error response returned by the API.

###### Example

    class Error extends \ActiveResource\ErrorAbstract
    {
        /**
        *   Get the error message returned by the API
        */
        public function getMessage()
        {
            return $this->getResponse()->getPayload()->error->message;
        }
        
        /**
        *   Get the validation error fields returned by the API
        */
        public function getFields()
        {
            return $this->getResponse()->getPayload()->error->fields;
        }
    }

## Models
Create your model classes and extend them from `\ActiveResource\Model`.

##### Properties
`connectionName` Name of connection to use. Defaults to `default`.

`resourceName` Name of the API resource URI. Defaults to name of class.

`resourceIdentifier` Name of the field to use as the ID. Defaults to `id`.

##### Static methods
`find` Find a single instance of a resource given its ID. Assumes payload will return *single* object.

`all` Get all instances of a resource. Assumes payload will return an *array* of objects.

`delete` Destroy (delete) a resource given its ID. 

`findThrough` Find a resource *through* another resource. For example, if you have to retrieve
a comment through its post `/posts/1234/comments/5678`.

`allThrough` Get all instances of a resource *through* another resource. For example, if you have
to retrieve comments through its post `/posts/1234/comments`.

##### Instance methods
`fill` Mass assign object properties with an array of key/value pairs.

`save` Save or update the instance.

`destroy` Destroy (delete) the instance.

`getResponse` Get the `Response` object for the last request.

`getError` Get the `Error` object for the last request.

`parseFind` Tells the Model class where in the payload to look for the data for a single resource. This method is called when using the
            `find` static method or the `save` instance method. The `parseFind` method accepts a
            single parameter containing the parsed/decoded payload and should return an object or an associative array containing the instance
            data. If you do not specify this method on your model, ActiveResource will pass the full payload to hydrate the model.
            Unless the API you are working with returns all relevant data in the root of the response, you *need* to implement
            this method. See Expected Data Format for more information.

`parseAll` Tells the Model class where in the payload to look for the data for an array of resources. This method is called when using the
           `all` static method. This method accepts a single parameter containing the parsed/decoded payload and should return an
           object or an associative array containing the instance data. If you do not specify this method on your model,
           ActiveResource will pass the full payload to hydrate the model. Unless the API you are working with returns all relevant data in the root of the response, you *need* to implement
           this method.See Expected Data Format for more information.

`includesOne` Tells the Model class that the response includes a single instance of another
model class. ActiveResource will then create an instance of the model and hydrate with the data.

`includesMany` Tells the Model class that the response includes an array of instances of another
model class. ActiveResource will then create a Collection of hydrated model instances.

You can also define `public` methods with the same name as an instance property that the model will send the data to. You
can then modify the data or more commonly, create a new model instance representing the data.
 
For example, say you are interacting with a blog API that has blog posts, users, and comments. You create the three model
classes representing the API resources.

###### Users

    class Users extends \ActiveResource\Model
    {
    }
     
###### Comments

    class Comments extends \ActiveResource\Model
    {
        public function author($data)
        {
            return $this->includesOne(Users::class, $data);
        }
    }

###### Posts

    class Posts extends \ActiveResource\Model
    {
        public function author($data)
        {
            return $this->includesOne(Users::class, $data);
        }
        
        public function comments($data)
        {
            return $this->includesMany(Comments::class, $data);
        }
        
        /**
        * You can find the blog post data in $.data.post in the payload
        */
        protected function parseFind($payload)
        {
            return $payload->data->post;
        }
        
        /**
        * You can find the collection of post data in $.data.posts in the payload
        */
        protected function parseAll($payload)
        {
            return $payload->data->posts;
        }
    }

Now grab blog post ID 7.
 
    `$posts = Posts::find(7);`
    
The response from the API looks like:

    {
        data: {
            post: {
                id: 7,
                title: "Blog post",
                body: "I am a short blog post",
                author: {
                    id: 123,
                    name: "John Doe",
                    email: "jdoe@example.com"
                },
                created_at: "2016-12-03 15:36:12",
                comments: [
                    {
                        id: 8,
                        body: "Great article!",
                        author: {
                            id: 567,
                            name: "Thomas Quigley",
                            email: "tquigley@example.com"
                        },
                        created_at: "2016-12-04 09:18:45"
                    },
                        
                    {
                        id: 9,
                        body: "Love the way your write",
                        author: {
                            id: 4178,
                            name: "Jane Johnson",
                            email: "jjohnson@example.com"
                        },
                        created_at: "2016-12-04 11:29:18"
                    }
                ]
            }
        }
    }            
    
ActiveResource will automatically hydrate model instances for comments and authors (users) on the Posts instance. These instances
 can then be modified and updated or even deleted.

## Middleware

You can modify and interact with the `Connection` object before it sends its request by using middleware.
A middleware class must implement a public `run` method and accepts the `Connection` instance
as its only parameter.

Keep in mind the connection request property is an instance of a Psr7\Request object which means
if you modify the request via any of its methods, it will return a *new* Psr7\Request instance.

###### Example
    
    class Authorize
    {
        public function run(\ActiveResource\Connection $connection)
        {
            $connection->request = $connection->request->withHeader('X-Foo-Header', 'Bar');
        }
    }
    
## Logging

You can activate request and response logging of every ActiveResource call by enabling the `log` option on a Connection.
To access the log data, call the `getLog` method on the connection. Due to memory footprint and security reasons, *do not*
use logging in production environments.

###### Example

    $connection = new Connection([
        'defaultUri' => 'https://someurl.com/v1/',
        'log' => true,
    ]);
    
    ConnectionManager::add('default', $connection);
    
    // Do some stuff, make some API calls

    $connection = ConnectionManager::get('default');
    $log = $connection->getLog();

## Quick Start Examples

### Find a single resource
    $user = User::find(123);

### Get all resources
    $users = User::all();

### Creating a new resource
    $user = new User;
    $user->name = 'Test User';
    $user->email = 'test@example.com';
    $user->save();
    
### Updating a resource
    $user = User::find(123);
    $user->status = 'INACTIVE';
    $user->save();
    
### Quickly assign properties
    $user = User::find($id);
    $user->fill([
        'name' => 'Buckley',
        'email' => 'buckley@example.com',
    ]);
    $user->save();
    
### Destroy (delete) a resource
    $user = User::find($id);
    $user->destory();

Or
    
    User::delete($id);

## FAQ
##### How do I send an Authorization header with every request?
If the Authorization scheme is either Basic or Bearer, the easiest way to add the header is in the
options array when creating the Connection object.

###### Example
        
        $options = [
            'defaultUri' => 'http://myapi.com/v2/',
            'defaultHeaders' => [
                'Authorization' => 'Bearer MYAPITOKEN',
            ],        
        ];
        
        $connection = new Connection($options);
        
For Authorization schemas that are a bit more complex (eg HMAC), use a Middleware approach.

##### The API response payload I am working with has all its data returned in the same path. Do I really need to have a parseFind and parseAll method on every model?
No, you don't. Create a BaseModel class with the parseFind and parseAll methods. Then extend
all your models from the BaseModel.

##### How do I handle JSON-API responses?
In your parse method on the Response object, you'll need to do a lot of work, but it can be done. ActiveResource
is looking for the parsed payload data to be in a specific format. See Expected Data Format for more information.

##### How do I access response headers?
You can access the Response object for the last API request via the Model's `getResponse` method. The Response object
has methods for retrieving response headers.

##### My API call is failing. How do I get access to the error response payload?
You can access the Error object for the last API request via the model's `getError` method.

##### How can I throw an exception on certain HTTP response codes?
The Response object has a protected array property called `throwable`. By default, HTTP Status 500 will throw an
`ActiveResourceResponseException`. You can override the array in your Response class with any set of HTTP status
codes you want. Or make it an empty array to *never* throw an exception.