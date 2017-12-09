# ActiveResource
Framework agnostic PHP ActiveResource implementation.

Use a RESTful resource based API like a database in an ActiveRecord pattern.

## Author's note
This project was started because I could not seem to find a good, maintained, and easy to use PHP based
ActiveResource package. Even though it's still in its infancy, I have personally used it on two
separate projects interacting with two completely different APIs: one built and maintained by me and
the other a 3rd party.

I hope you will find it as useful as I have.

If you have any suggestions or potential feature requests, feel free to ping me [brent@brentscheffler.com](brent@brentscheffler.com)

## Installation

    composer require nimbly/activeresource
    
## Quick start

This quick start guide assumes the API:

1. Accepts and responds JSON (application/json)
2. Uses HTTP response codes to indicate response status (200 OK, 404 Not Found, 400 Bad Request, etc)
3. Is resource based - you interact with nouns (resources), not verbs

If the API you are working with doesn't have these assumptions, that's okay, just be sure to read the documentation
for full configuration options, custom `Response` and `Error` classes, and Middleware.

#### Create the connection
    
```php

    $options = [
        Connection::OPTION_BASE_URI => 'https://someapi.com/v1/',
        Connection::OPTION_DEFAULT_HEADERS => [
            'Authorization' => 'Bearer MYAPITOKEN',
        ]
    ];
    
    $connection = new Connection($options);
```
    
#### Add connection to ConnectionManager

```php
    
    ConnectionManager::add('default', $connection);
```
    
#### Create your models

```php

    use ActiveResource\Model;
    
    /**
    * Because the class name is "Users", ActiveResource assumes the API endpoint for this resource is "users".
    */
    class Users extends Model
    {
        /**
        * The single user object can be found at $.data.user
        *
        * Sample response payload:
        *
        *    {
        *        "data": {
        *            "user": {
        *                "id": "1",
        *                "name": "Foo Bar",
        *                "email": "foo@bar.com"
        *            }
        *        }
        *    }
        *         
        *
        */
        protected function parseFind($payload)
        {
            return $payload->data->user;
        }
        
        protected function parseAll($payload)
        {
            return $payload->data->users;
        }

    }
    
    
    /**
    * Because the class name is "Posts", ActiveResource assumes the API endpoint for this resource is "posts".
    */
    class Posts extends Model
    {
        // Manually set the endpoint for this resource. Now ActiveResource will hit "blogs" when making calls
        // from this model.
        protected $resourceName = 'blogs';
    
    
        /**
        * A blog post has an author object embedded in the response that
        * maps to a User object.
        */
        protected function author($data)
        {
            return $this->includesOne(Users::class, $data);
        }
    
        protected function parseFind($payload)
        {
            return $payload->data->post;
        }
        
        protected function parseAll($payload)
        {
            return $payload->data->posts;
        }
         
    }
    
    class Comments extends Model
    {
    
        /**
        * A comment has an author object embedded in the response that
        * maps to a User object.
        */
        protected function author($data)
        {
            return $this->includesOne(Users::class, $data);
        }
        
        
        protected function parseFind($payload)
        {
            return $payload->data->comment;
        }
        
        protected function parseAll($payload)
        {
            return $payload->data->comments;
        }
        
    }
```

#### Use your models

```php

    $user = new User;
    $user->name = 'Brent Scheffler';
    $user->email = 'brent@brentscheffler.com';
    $user->save();
    
    $post = new Posts;
    $post->title = 'Blog post';
    $post->body = 'World\'s shortest blog post';
    $post->author_id = $user->id;
    $post->save();
    
    // Update the author (user)
    $post->author->email = 'brent@nimbly.io';
    
    // Oops, save failed... Wonder what happened.
    if( $post->author->save() == false )
    {
        // Looks like that email address is already being used
        $code = $post->getResponse()->getStatusCode(); // 409
        $error = $post->getResponse()->getStatusPhrase(); // Conflict    
    }
    
    // Get user ID=1
    $user = User::find(1);
    
    // Update the user
    $user->status = 'inactive';
    $user->save();

    // Get all the users
    $users = Users::all();
    
    // Pass in some query params to find only active users
    $users = Users::all(['status' => 'active']);

    // Delete user ID=1
    $user->destroy();
    
    // Get the response code
    $statusCode = $user->getResponse()->getStatusCode(); // 204 No Content

    // Pass in a specific header for this call
    $post = Posts::all([], ['X-Header-Foo' => 'Bar']);
    
    // Get blog post ID=1
    $post = Posts::find(1);
    
    // Get all comments through Posts resource. The effective query would be GET#/blogs/1/comments
    $comments = Comments::allThrough($post);
    
    // Or...
    $comments = Comments::allThrough("blogs/1");
```

#### That's it
That's all there really is to using ActiveResource. Hopefully your API is mostly [RMM Level 2](https://martinfowler.com/articles/richardsonMaturityModel.html#level2)
 making configuration a breeze.

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

`defaultQueryParams` *array* Key => value pairs to include in the URL query part with every request.

`defaultContentType` *string* The default Content-Type request header to use for requests that include a message body
(PUT, POST, PATCH). Defaults to `application/json`. Some common content type strings are available as class constants on
the `Connection` class.

`responseClass` *string* Class name of the Response class to use for parsing responses including headers and body. Default
is `ActiveResource\Response` class. See Response section for more info.

`collectionClass` *string* Class name of a Collection class to use for handling arrays of models returned in a response.
The Collection class must accept an array of data in its constructor. Default is `ActiveResource\Collection` class.
Set to `null` to return a simple PHP array of model instances.

This option is useful if you're working with a framework, library, or custom code that has a robust Collection utility like
Laravel's `Illuminate\Support\Collection`.

`updateMethod` *string* HTTP method to use for updates. Defaults to `put`.

`updateDiff` *boolean* Whether ActiveResource can send just the modified fields of the resource on an update.

`middleware` *array* An array of middleware classes to execute. See Middleware section for more info.

`log` *boolean* Tell ActiveResource to log all requests and responses. Defaults to `false`. Do not use this option
in production environments. You can access the log via the Connection getLog() method via the ConnectionManager.


All of the above string options are available as class constants on the `Connection` class.

##### HttpClient
An optional instance of `GuzzleHttp\Client`. If you do not provide an instance, one will be created automatically
with no options set.

###### Example

```php

    $options = [
        Connection::OPTION_BASE_URI => 'http://api.someurl.com/v1/',
        Connection::OPTION_UPDATE_METHOD => 'patch',
        Connection::OPTION_UPDATE_DIFF => true,
        Connection::OPTION_RESPONSE_CLASS => \My\Custom\Response::class,
        Connection::OPTION_MIDDLEWARE => [
            \My\Custom\Middleware\Authorize::class,
            \My\Custom\Middleware\Headers::class,
        ]
    ];
    
    $connection = new \ActiveResource\Connection($options);
```

### ConnectionManager

Use `ConnectionManager::add` to add one or more Connection instances. This allows you to use ActiveResource with any
number of APIs within your code. If you interact mostly with a single API, you can set the name to `default` without
needing to specify the connection name on each of your models.

If you *do* need to interact with multiple APIs, be sure to give them distinct connection names. You'll likely want to
create an abstract BaseModel with the connectionName property set and extend your actual models from the BaseModel.

###### Example
    
```php

    ConnectionManager::add('yourConnectionName', $connection);
```


## Response
Although ActiveResource comes with a basic Response class (that simply JSON decodes the response body), each and every
API responds with its own unique payload and encoding and it is recommended you provide your own custom response class that
extends `\ActiveResource\ResponseAbstract`. See Connection option `responseClass`.

### Required method implementation
`decode` Accepts the raw payload contents from the response. Should return an array or \StdClass
object representing the data. See Expected Data Format for more details.

`isSuccessful` Should return a boolean indicating whether the request was successful or not. Some APIs
do not adhere to strict REST patterns and may return an HTTP Status Code of 200 for all requests. In this
case there is usually a property in the payload indicating whether the request was successful or not.
 
The Response object is also a great way to include any other methods to access non-payload
related data or headers. It all depends on what data is in the response body for the API you
are working with.

###### Example

```php

    class Response extends \ActiveResource\ResponseAbstract
    {
        public function decode($payload)
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
```
    

## Expected data format
In order for ActiveResource to properly hydrate your Model instances, the decoded response payload must be formatted in
 the following pattern:
 
```json
 
     {
         "property1": "value",
         "property2": "value",
         "property3": "value",
         "related_single_resource": {
             "property1": "value",
             "property2": "value"
         },
         "related_multiple_resources": [
             {
                 "property1": "value",
                 "property2": "value"
             }
         ]
     }
```

###### Example

```json

    {
        "id": "1234",
        "title": "Blog post",
        "body": "This is a blog post",
        "author": {
            "id": "32135",
            "name": "John Doe",
            "email": "jdoe@example.com"
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
```

If the API you are working with does not have its data formatted in this manor - you will need to transform it so that it is.
This can (and should) be done in your `Response` class `decode` method.

## Models
Create your model classes and extend them from `\ActiveResource\Model`.

##### Properties
`connectionName` Name of connection to use. Defaults to `default`.

`resourceName` Name of the API resource URI. Defaults to lowercase name of class.

`resourceIdentifier` Name of the property to use as the ID. Defaults to `id`.

`readOnlyProperties` Array of property names that are read only. When set to null or empty array, all properties are writable.

`fillableProperties` When set to array of property names, only these properties are allowed to be mass assigned when calling the fill() method.
If null, *all* properties can be mass assigned.

`excludedProperties` Array of property names that are excluded when saving/updating model to API. If null or empty array, all properties can be sent when saving model.

##### Static methods
`find` Find a single instance of a resource given its ID. Assumes payload will return *single* object.

`all` Get all instances of a resource. Assumes payload will return an *array* of objects.

`delete` Destroy (delete) a resource given its ID. 

`findThrough` Find a resource *through* another resource. For example, if you have to retrieve
a comment through its post `/posts/1234/comments/5678`.

`allThrough` Get all instances of a resource *through* another resource. For example, if you have
to retrieve comments through its post `/posts/1234/comments`.

`connection` Get the model's `Connection` instance.

`request` Get the last request object.

`response` Get the last response object.

##### Instance methods
`fill` Mass assign object properties with an array of key/value pairs.

`save` Save or update the instance.

`destroy` Destroy (delete) the instance.

`getConnection` Get the model's `Connection` instance.

`getRequest` Get the `Request` object for the last request.

`getResponse` Get the `Response` object for the last request.

`includesOne` Tells the Model class that the response includes a single instance of another
model class. ActiveResource will then create an instance of the model and hydrate with the data.

`includesMany` Tells the Model class that the response includes an array of instances of another
model class. ActiveResource will then create a Collection of hydrated model instances.

`parseFind` Tells the Model class where in the response payload to look for the data for a single resource. This method
            is called when using the `find` and `findThrough` static methods and the `save` instance method. The `parseFind` method accepts
            a single parameter containing the decoded payload and should return an object or an associative array
            containing the instance data. If you do not specify this method on your model, ActiveResource will pass the
            full payload to hydrate the model. Unless the API you are working with returns all relevant data in the root
            of the response, you *must* implement this method. See Expected Data Format for more information.

`parseAll` Tells the Model class where in the response payload to look for the data for an array of resources. This
            method is called when using the `all` and `allThrough` static methods. This method accepts a single
            parameter containing the decoded response payload and should return an object or an associative array
            containing the instance data. If you do not specify this method on your model, ActiveResource will pass the
            full payload to hydrate the model. Unless the API you are working with returns all relevant data in the root
            of the response, you *must* implement this method. See Expected Data Format for more information.

`encode` Called before sending a request to format and encode the model instance into a request body. Defaults to json_encode().
You should override this method if you need a different format or encoding for the API you are working with.

`reset` Resets the state of the model to its original condition - i.e. all modified properties are reverted.

`original` Returns the original value of a property.


You can also define `public` methods with the same name as an instance property that the model will send the data to.
You can then modify the data or more commonly, create a new model instance representing the data.
 
For example, say you are interacting with a blog API that has blog posts, users, and comments. You create the three model
classes representing the API resources.

###### Users

```php

    class Users extends \ActiveResource\Model
    {
    }
```
     
###### Comments

```php

    class Comments extends \ActiveResource\Model
    {
        public function author($data)
        {
            return $this->includesOne(Users::class, $data);
        }
    }
```

###### Posts

```php

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
```

Now grab blog post ID 7.
 
```php

    $posts = Posts::find(7);
```
    
The response from the API looks like:

```php

    {
        "data": {
            "post": {
                "id": 7,
                "title": "Blog post",
                "body": "I am a short blog post",
                "author": {
                    "id": 123,
                    "name": "John Doe",
                    "email": "jdoe@example.com"
                },
                "created_at": "2016-12-03 15:36:12",
                "comments": [
                    {
                        "id": 8,
                        "body": "Great article!",
                        "author": {
                            "id": 567,
                            "name": "Thomas Quigley",
                            "email": "tquigley@example.com"
                        },
                        "created_at": "2016-12-04 09:18:45"
                    },
                        
                    {
                        "id": 9,
                        "body": "Love the way your write",
                        "author": {
                            "id": 4178,
                            "name": "Jane Johnson",
                            "email": "jjohnson@example.com"
                        },
                        "created_at": "2016-12-04 11:29:18"
                    }
                ]
            }
        }
    }
```
    
ActiveResource will automatically hydrate model instances for comments and authors (users) on the Posts instance. These
instances can then be modified and updated or even deleted.

## Middleware

Middleware in ActiveResource is managed by the excellent [Onion](https://github.com/esbenp/onion) package - "a standalone middleware library without dependencies".

Your middleware classes must implement Onion's LayerInterface class and implement the `peel` method.

The input object is an `ActiveResource\Request` instance. The output is an instance of `ActiveResource\ResponseAbstract`.

###### Example
    
```php

    class Authorize implements LayerInterface
    {
        /**
        *
        *  @param \ActiveResource\Request $object
        */
        public function peel($object, \Closure $next)
        {
            // Add a query param to the URL (&foo=bar)
            $object->setQuery('foo', 'bar');
            
            // Do some HMAC authorization logic here
            // ...
            // ...            
            
            // Now add the HMAC headers
            $object->setHeader('X-Hmac-Timestamp', $timestamp);
            $object->setHeader('Authorization', "HMAC {$hmac}");
            
            // Send the request off to the next layer
            $response = $next($object);
            
            // Now let's slip in a spoofed header into the response object
            $response->setHeader('X-Spoofed-Response-Header', 'Foo');
            
            // How about we completely change the response status code?
            $response->setStatusCode(500);
            
            // Return the response
            return $response;
        }
    }
```
    
## Logging

You can activate request and response logging of every ActiveResource call by enabling the `log` option on a `Connection`.
To access the log data, call the `getLog` method on the connection. Due to memory footprint and security reasons, *do not*
use logging in production environments.

###### Example

```php
    $connection = new Connection([
        Connection::OPTION_BASE_URI => 'https://someurl.com/v1/',
        Connection::OPTION_LOG => true,
    ]);
    
    ConnectionManager::add('yourConnectionName', $connection);
    
    $post = Post::find(12);

    $connection = ConnectionManager::get('yourConnectionName');
    $log = $connection->getLog();
    
        // Or...

    
    $post->getConnection()->getLog();

       // Or...
    
    Post::connection()->getLog();
```

## Quick Start Examples

### Find a single resource

```php

    $user = User::find(123);
```

### Get all resources

```php

    $users = User::all();
```

### Creating a new resource

```php

    $user = new User;
    $user->name = 'Test User';
    $user->email = 'test@example.com';
    $user->save();
```
    
### Updating a resource

```php

    $user = User::find(123);
    $user->status = 'INACTIVE';
    $user->save();
```
    
### Quickly assign properties

```php

    $user = User::find($id);
    $user->fill([
        'name' => 'Buckley',
        'email' => 'buckley@example.com',
    ]);
    $user->save();
```
    
### Destroy (delete) a resource

```php

    $user = User::find($id);
    $user->destory();
    
    // Or...
    
    User::delete($id);
```

## FAQ
##### How do I send an Authorization header with every request?
If the Authorization scheme is either Basic or Bearer, the easiest way to add the header is in the
`defaultHeaders` option array when creating the Connection object.

###### Example
        
```php

        $options = [
            Connection::OPTION_BASE_URI => 'http://myapi.com/v2/',
            Connection::OPTION_DEFAULT_HEADERS => [
                'Authorization' => 'Bearer MYAPITOKEN',
            ],
        ];

        $connection = new Connection($options);
```
        
For Authorization schemas that are a bit more complex (eg HMAC), use a Middleware approach. See the Middleware section
for more information.

##### The API response payload I am working with has all its data returned in the same root path. Do I really need to have a parseFind and parseAll method on every model?
No, you don't. Create an abstract BaseModel class with the `parseFind` and `parseAll` methods. Then extend
all your models from that BaseModel.

##### How do I handle JSON-API responses?
In your `Response` object `decode` method you'll need to do a lot of work, but it can be done. ActiveResource
is looking for the decoded payload data to be in a specific format. See Expected Data Format for more information. For
requests that need to be in JSON-API format, you'll need to do a lot of work in the Model `encode` method.

##### How do I access the response object to pull out headers, status code, or parse and error payload?
You can access the `Response` object for the last API request via the Model's `getResponse` instance method.
The `Response` object has methods for retrieving response headers, status, and body. Alternatively, you can also access
the response object statically via the model's `response` static method.

##### How can I throw an exception on certain HTTP response codes?
The `Response` object has a protected array property called `throwable`. By default, HTTP Status 500 will throw an
`ActiveResourceResponseException`. You can override the array in your `Response` class with any set of HTTP status
codes you want. Or make it an empty array to *never* throw an exception.

Connection issues including timeouts will *always* throw a `GuzzleHttp\Exception\ConnectException`.

##### The API I am working with has an endpoint that simply does not conform to the ActiveResource pattern, how can I call the endpoint?
You can send a custom request by getting the `Connection` object instance and using the `buildRequest` and `send`
methods.

```php

    $connection = ConnectionManager::get('yourConnectionName');
    $request = $connection->buildRequest('post', '/some/oddball/endpoint', ['param1' => 'value1'], ['foo' => 'bar', 'fox' => 'sox'], ['X-Custom-Header', 'Foo']);
    $response = $connection->send($request);
```
    
You'll get an instance of a `ResponseAbstract` object back.