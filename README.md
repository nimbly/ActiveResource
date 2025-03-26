# ActiveResource

[![Latest Stable Version](https://img.shields.io/packagist/v/nimbly/activeresource.svg?style=flat-square)](https://packagist.org/packages/nimbly/activeresource)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/nimbly/activeresource/coverage.yml?style=flat-square)](https://github.com/nimbly/Capsule/actions/workflows/coverage.yml)
[![Codecov branch](https://img.shields.io/codecov/c/github/nimbly/activeresource/master?style=flat-square)](https://app.codecov.io/github/nimbly/Capsule)
[![License](https://img.shields.io/github/license/nimbly/activeresource.svg?style=flat-square)](https://packagist.org/packages/nimbly/activeresource)

Use a RESTful API in an ActiveRecord pattern.

## Requirements

* PHP 8.2+
* ext-json
* RMM Level 2 compliant API (see [Richardson Maturity Model](https://martinfowler.com/articles/richardsonMaturityModel.html))

## Install

```bash
composer require nimbly/activeresource
```

## Quick start

### Define your API connection

```php
$connection = new Connection(
	host: "https://api.example.com",
	headers: [
		"Content-Type" => "application/json",
		"Authorization" => "Bearer {$token}"
	]
);
```

### Add the Connection

```php
ConnectionManager::init(["default" => $connection]);
```

### Define your first Resource

```php
class Book extends Resource
{}
```

### Retrieve a record

```php
$book = Book::find("123");
```

ActiveResource will attempt to retrieve (`GET`) the `book` resource from the API with the following: `https://api.example.com/book/123`.

If found, a fully hydrated `Book` class instance is returned using the response from the API.

### Get all records

```php
$books = Book::all();

foreach( $books as $book ){
	echo $book->title . "\n";
}
```

### Create a new record

```php
$book = new Book([
	"title" => "Do Androids Dream of Electric Sheep?",
	"author" => "Philip K Dick",
]);
```

### Save a record

```php
$book->publisher = "Penguin";
$book->published_at = "1983-11-12";
$book->save();
```

### Delete a record

```php
$book->delete();
```

## Connection

The `Connection` instance represents a single specific API integration. You must pass the hostname and base URI (if any). In addition to the host name, you can also specify default headers and query parameters to be included with each request, and other options that alter how the underlying API calls should be made.

```php
$connection = new Connection(
	host: "https://api.example.com/v1/",
	headers: [
		"Content-Type" => "application/json",
		"Authorization" => "Bearer {$token}"
	],
	options: [
		Connection::UPDATE_METHOD => "patch",
		Connection::UPDATE_DIFF => true,
	]
);
```

### Options

`Connection::OPTION_CREATE_METHOD` (string) The HTTP method to use when creating new resources. Defaults to `POST`.
`Connection::OPTION_UPDATE_METHOD` (string) The HTTP method to use when updating resources. Defaults to `PUT`.
`Connection::OPTION_UPDATE_DIFF` (boolean) Set to true if the API allows only sending the properties that have changed when updating. Defaults to `false`.
`Connection::OPTION_HTTP_VERSION` (string) The HTTP version to use when making API calls. Defaults to `1.1`.
`Connection::OPTION_SERIALIZER` => (callable) The callable to use when serializing request body data. Defaults to `json_encode`.
`Connection::OPTION_DESERIALIZER` => (callable) The callable to use when deserializing response body data. Defaults to `json_decode`.

## Connection Manager

The `ConnectionManager` manages your various connections and issues the underlying HTTP calls, serialization, deserialization, and event dispatching when saving and deleting resources.

ActiveResource uses PSR-7 and PSR-17 instances to issue HTTP calls and handle the responses. You can bring your own implementations (eg, Guzzle). If none are provided, `nimbly/Shuttle` and `nimbly/Capsule` will be used.

In addition to PSR-7 and PSR-17, a PSR-18 instance can be provided to dispatch events during the save and deletion lifecycle.

```php
ConnectionManager::init(
	connections: [
		"default" => $connection1,
		"other" => $connection2,
	],
	httpClient: $httpClient,
	requestFactory: $factory,
	streamFactory: $factory,
	eventDispatcher: $dispatcher,
);
```

## Resources

### Retrieving a single resource

```php
$book = Book::find($id);
```

### Retrieving multiple resources

```php
$books = Book::all();
```

A call to `all` will return a `ResourceCollection` instance that contains an array of the resources but also any metadata that was returned in the response as well as the response headers. The `ResourceCollection` implements `ArrayAccess`, `Iterator`, and `Countable` and can be used in `for` or `foreach` loops and calls to `count()`.

```php
$books = Book::all();

echo "There are " . count($books) . " in this response.";

foreach( $books as $book ){
	// ...
}
```

Unless the API directly returns an array of the resources, ActiveResource will need to know where to find the actual resources in the response data, you will need to add a `#[CollectionProperty]` attribute on the class.

```php
#[CollectionProperty("results")]
class Book extends Resource
{}
```

To retrieve response metadata, simply call the `getMeta` on the `ResourceCollection` instance.

```php
$books = Book::all();

$books->getMeta("current_page");
$books->getMeta("total_pages");
```

### Creating

To create a new instance, simply instantiate the class, assign values (either in the constructor or directly), and call the `save()` method.

```php
$book = new Book([
	"title" => "Do Androids Dream of Electric Sheep?",
	"author" => "Philip K Dick"
]);

$book->save();
```

### Updating

To update a resource, simply retrieve the resource from the API, make your changes, and call the `save()` method.

```php
$book = Book::find("123");
$book->published_at = "2000-03-24";
$book->save();
```

### Loading a resource from cache

You can take a cached or shallow copy version of your resource and load it directly by calling the `make` method. If the cached version has an identifier set, subsequent calls to `save()` will update it. If no identifier exists, ActiveResource will assume it needs to be created when calling `save()`.

```php
$book = Book::make($cache);
$book->published_at = "2000-03-24";
$book->save();
```

### Fillable

In order to bulk load values into a resource, you must define which fields *can* be bulk filled. Use the `$fillableProperties` class property to declare property names that can be bulk filled.

```php
class Book extends Resource
{
	protected array $fillableProperties = ["isbn", "title", "author"];
}
```

Load values directly in constructor...

```php
$book = new Book([
	"isbn" => "123123313",
	"title" => "Do Androids Dream of Electric Sheep?",
	"author" => "Philip K Dick"
]);

$book->save();
```

Update values with `fill`...

```php
$book->fill($request->getParsedBody());
$book->save();
```

You can always directly set property values, regardless of whether they are listed in the `fillableProperties` array or not.

```php
class Book extends Resource
{
	protected array $fillableProperties = ["isbn", "title", "author"];
}
```

```php
$book = new Book;
$book->published_at = "2000-03-24";
```

### Custom setters and getters

You can create custom setters and getters that will be invoked when accessing or assigning resource properties. Simply name them as `get{Name}Property` and `set{Name}Property` where `{Name}` is the name of the property. Both methods will be passed the raw unmodified value and you must return the modified value.

```php
protected function getPublishedAtProperty(string $date): DateTime
{
	return new DateTime($date);
}
```

```php
protected function setPasswordProperty(#[SensitiveParameter] string $password): string
{
	return \password_hash($password, PASSWORD_BCRYPT);
}
```

**NOTE:** These methods cannot be `private` (i.e. they must be `protected` or `public`.)

### Computed values

You can use a custom getter to provide computed values that have no base or raw value themselves by not providing any function parameters.

```php
class Book extends Resource
{
	protected function getAgeProperty(): int
	{
		return (new DateTime)->diff(new DateTime($this->created_at))->y;
	}
}
```

```php
$book = Book::find("123");
echo $book->age;
```

### Excluded properties

Sometimes you need class properties that should be excluded from being sent with API requests when saving or updating. The `Resource` class provides an `$excludedProperties` property to accomplish this. Excluded properties do not prevent the property from being consumed when retrieving records from the API. Excluded properties can still use custom getters and setters.

```php
class Book extends Resource
{
	protected array $excludedProperties = ["age"];

	protected function getAgeProperty(): int
	{
		return (new DateTime)->diff(new DateTime($this->published_at))->y;
	}
}
```

### Resource Attributes

It's always better to adhere to convention over configuration, but ActiveResource does provide class attributes that can override convention.

#### Connection name

By default, ActiveResource will attempt to use the `default` connection from the connection manager. However, if you would like to use a different connection, you can add the `#[ConnectionName]` class attribute to your resource.

```php
#[ConnectionName("segment")]
class User extends Resource
{}
```

### Resource name

By default, ActiveResource will use the lower case name of the class as the resource name. However, you can override this behavior by using the `#[ResourceName]` class attribute.

```php
#[ResourceName("library_books")]
class Book extends Resource
{}
```

### Resource identifier

By default, ActiveResource assumes the identifier of the resource is contained within the `id` property of the response. You can override this behavior by using the `#[ResourceIdenfitier]` class attribute.

```php
#[ResourceIdentifier("isbn")]
class Book extends Resource
{}
```

### Collection property

Many APIs return a slightly different response body when retrieving multiple records. For example, the response may contain some meta data about the page number, the total number of available records, and finally a property that actually contains the records themselves.

For example, we make a call to get all the records of books...

```php
$books = Book::all();
```

And are returned the following payload (which is typical for paginated results)...

```json
{
	"count": 3,
	"page": 1,
	"total": 235,
	"results": [
		{
			"id": 123,
			"title": "Do Androids Dream of Electric Sheep?",
			"author": "Philip K Dick"
		},

		{
			"id": 345,
			"title": "Breakfast of Champions",
			"author": "Kurt Vonnegut"
		},

		{
			"id": 256,
			"title": "Less Than Zero",
			"author": "Bret Easton Ellis"
		}
	]
}
```

We need to let ActiveResource know to look in the `results` property for the actual returned resources.

```php
#[CollectionProperty("results")]
class Book extends Resource
{}
```

## Response headers

Sometimes, it's important to capture response headers from the API. ActiveResource will attach the response headers to the retrieved `Resource` or `ResourceCollection`.

```php
$book = Book::find($id);

if( $book->getResponseHeader("X-Foo") === "bar" ) {
	//...
}
```

```php
$book = Book::find($id);

$headers = $book->getResponseHeaders();
```

For calls to `all()`, the response headers will be attached to the `ResourceCollection` instance.

```php
$books = Book::all();

if( $books->getResponseHeader("X-Foo") === "bar" ){
	//...
}
```

## Events

You can tap into the lifecycle events for saving and deleting resources by providing a PSR-X Event Dispatcher instance and subscribing to any of the following:

`ResourceSavingEvent` is triggered just before the API call to save/update the resource.
`ResourceSavedEvent` is triggered after the resource has been succesfully saved.
`ResourceDeletingEvent` is triggered just before the API cal to delete the resource.
`ResourceDeletedEvent` is triggered after the resource has been successfully deleted.