<?php

namespace Nimbly\ActiveResource\Tests;

use DateTime;
use Nimbly\Capsule\Request;
use Nimbly\Shuttle\Shuttle;
use Nimbly\Capsule\Response;
use Nimbly\Announce\Dispatcher;
use PHPUnit\Framework\TestCase;
use Nimbly\Capsule\ResponseStatus;
use Nimbly\ActiveResource\Resource;
use Nimbly\Shuttle\RequestException;
use Nimbly\ActiveResource\Connection;
use Nimbly\Shuttle\Handler\MockHandler;
use Nimbly\ActiveResource\ConnectionManager;
use Nimbly\ActiveResource\ResourceException;
use Nimbly\ActiveResource\ResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use Nimbly\ActiveResource\Tests\Resources\Book;
use Nimbly\ActiveResource\Events\ResourceSavedEvent;
use Nimbly\ActiveResource\Events\ResourceSavingEvent;
use Nimbly\ActiveResource\Events\ResourceDeletedEvent;
use Nimbly\ActiveResource\Events\ResourceDeletingEvent;
use Nimbly\ActiveResource\Events\ResourceEventAbstract;
use PHPUnit\Framework\Attributes\BackupStaticProperties;

#[CoversClass(Resource::class)]
#[BackupStaticProperties(false)]
class ResourceTest extends TestCase
{
	public function test_constructor_fills_data()
	{
		$attributes = [
			"isbn" => "0345404475",
			"title" => "Do Androids Dream of Electric Sheep?",
			"author" => "Phillip K. Dick",
			"publisher" => "Del Rey",
			"published_at" => "1996-05-28"
		];

		$book = new Book($attributes);

		$this->assertEquals(
			$attributes,
			$book->toArray()
		);
	}

	public function test_hydrate_sets_properties()
	{
		$book = new Book;

		$attributes = [
			"isbn" => "0345404475",
			"title" => "Do Androids Dream of Electric Sheep?",
			"author" => "Phillip K. Dick",
			"publisher" => "Del Rey",
			"published_at" => "1996-05-28"
		];

		$book->hydrate($attributes);

		$this->assertEquals(
			$book->toArray(),
			$attributes
		);

		$this->assertEmpty(
			$book->getModifiedProperties()
		);
	}

	public function test_hydrate_uses_property_mutator(): void
	{
		$attributes = [
			"isbn" => "0345404475",
			"title" => "Do Androids Dream of Electric Sheep?",
			"author" => "Phillip K. Dick",
			"status" => "IN STOCK",
			"publisher" => "Del Rey",
			"published_at" => "1996-05-28"
		];

		$book = Book::make($attributes);

		$this->assertEquals(
			"in stock",
			$book->status
		);
	}

	public function test_make(): void
	{
		$attributes = [
			"isbn" => "0345404475",
			"title" => "Do Androids Dream of Electric Sheep?",
			"author" => "Phillip K. Dick",
			"publisher" => "Del Rey",
			"published_at" => "1996-05-28"
		];

		$book = Book::make($attributes);

		$this->assertEquals(
			$book->toArray(),
			$attributes
		);

		$this->assertEmpty(
			$book->getModifiedProperties()
		);
	}

	public function test_fill(): void
	{
		$attributes = [
			"isbn" => "0345404475",
			"title" => "Do Androids Dream of Electric Sheep?",
			"author" => "Phillip K. Dick",
			"publisher" => "Del Rey",
			"status" => "in stock",
			"published_at" => "1996-05-28"
		];

		$book = Book::make($attributes);
		$book->fill(["status" => "out of stock"]);

		$this->assertEquals(
			"out of stock",
			$book->status
		);
	}

	public function test_fill_only_fillable_on_declared_properties(): void
	{
		$attributes = [
			"isbn" => "0345404475",
			"title" => "Do Androids Dream of Electric Sheep?",
			"author" => "Phillip K. Dick",
			"publisher" => "Del Rey",
			"status" => "in stock",
			"published_at" => "1996-05-28"
		];

		$book = Book::make($attributes);
		$book->fill(["id" => "f76f6cb7-ee89-453d-aa2a-515612adbe57"]);

		$this->assertNull($book->id);
	}

	public function test_get_modified_properties(): void
	{
		$attributes = [
			"isbn" => "0345404475",
			"title" => "Do Androids Dream of Electric Sheep?",
			"author" => "Phillip K. Dick",
			"publisher" => "Del Rey",
			"status" => "in stock",
			"published_at" => "1996-05-28"
		];

		$book = Book::make($attributes);
		$book->id = "f76f6cb7-ee89-453d-aa2a-515612adbe57";
		$book->status = "out of stock";

		$this->assertEquals(
			[
				"id" => "f76f6cb7-ee89-453d-aa2a-515612adbe57",
				"status" => "out of stock",
			],
			$book->getModifiedProperties()
		);
	}

	public function test_reset(): void
	{
		$attributes = [
			"isbn" => "0345404475",
			"title" => "Do Androids Dream of Electric Sheep?",
			"author" => "Phillip K. Dick",
			"publisher" => "Del Rey",
			"status" => "in stock",
			"published_at" => "1996-05-28"
		];

		$book = Book::make($attributes);
		$book->id = "f76f6cb7-ee89-453d-aa2a-515612adbe57";
		$book->status = "out of stock";

		$book->reset();

		$this->assertEmpty($book->getModifiedProperties());
	}

	public function test_magic_getter(): void
	{
		$attributes = [
			"isbn" => "0345404475",
			"title" => "Do Androids Dream of Electric Sheep?",
			"author" => "Phillip K. Dick",
			"publisher" => "Del Rey",
			"status" => "in stock",
			"published_at" => "1996-05-28"
		];

		$book = Book::make($attributes);

		$this->assertEquals(
			"0345404475",
			$book->isbn
		);
	}

	public function test_get_property(): void
	{
		$attributes = [
			"isbn" => "0345404475",
			"title" => "Do Androids Dream of Electric Sheep?",
			"author" => "Phillip K. Dick",
			"publisher" => "Del Rey",
			"status" => "in stock",
			"published_at" => "1996-05-28"
		];

		$book = Book::make($attributes);

		$this->assertEquals(
			"0345404475",
			$book->getProperty("isbn")
		);
	}

	public function test_get_property_with_accessor(): void
	{
		$attributes = [
			"isbn" => "0345404475",
			"title" => "Do Androids Dream of Electric Sheep?",
			"author" => "Phillip K. Dick",
			"publisher" => "Del Rey",
			"status" => "in stock",
			"published_at" => "1996-05-28",
			"created_at" => "2025-03-02T14:10:34Z"
		];

		$book = Book::make($attributes);

		$this->assertInstanceOf(
			DateTime::class,
			$book->getProperty("created_at")
		);
	}

	public function test_magic_setter(): void
	{
		$book = new Book;
		$book->id = "f76f6cb7-ee89-453d-aa2a-515612adbe57";

		$this->assertEquals(
			"f76f6cb7-ee89-453d-aa2a-515612adbe57",
			$book->getProperty("id")
		);
	}

	public function test_set_property(): void
	{
		$book = new Book;
		$book->setProperty("id", "f76f6cb7-ee89-453d-aa2a-515612adbe57");

		$this->assertEquals(
			"f76f6cb7-ee89-453d-aa2a-515612adbe57",
			$book->getProperty("id")
		);
	}

	public function test_set_property_with_mutator(): void
	{
		$book = new Book;
		$book->setProperty("status", "OUT OF STOCK");

		$this->assertEquals(
			"out of stock",
			$book->getProperty("status")
		);
	}

	public function test_to_array(): void
	{
		$attributes = [
			"isbn" => "0345404475",
			"title" => "Do Androids Dream of Electric Sheep?",
			"author" => "Phillip K. Dick",
			"status" => "IN STOCK",
			"publisher" => "Del Rey",
			"published_at" => "1996-05-28"
		];

		$book = Book::make($attributes);
		$book->id = "f76f6cb7-ee89-453d-aa2a-515612adbe57";

		$this->assertEquals(
			[
				"id" => "f76f6cb7-ee89-453d-aa2a-515612adbe57",
				"isbn" => "0345404475",
				"title" => "Do Androids Dream of Electric Sheep?",
				"author" => "Phillip K. Dick",
				"status" => "in stock",
				"publisher" => "Del Rey",
				"published_at" => "1996-05-28"
			],
			$book->toArray()
		);
	}

	public function test_find(): void
	{
		ConnectionManager::init(
			connections: ["default" => new Connection("https://api.example.com")],
			httpClient: new Shuttle(
				handler: new MockHandler([
					function(Request $request): Response {
						$book = [
							"id" => "6af9a9f5-0251-4e09-be1d-4e36ecb242fa",
							"request" => [
								"method" => $request->getMethod(),
								"uri" => (string) $request->getUri(),
								"headers" => $request->getHeaders(),
							],
						];

						return new Response(ResponseStatus::OK, \json_encode($book));
					}
				])
			)
		);

		$book = Book::find(123, ["p" => 1], ["X-Foo" => "Bar"]);

		$this->assertEquals(
			"books/6af9a9f5-0251-4e09-be1d-4e36ecb242fa",
			$book->getResourceUri()
		);

		$this->assertEquals(
			"GET",
			$book->request->method
		);

		$this->assertEquals(
			"https://api.example.com/books/123?p=1",
			$book->request->uri,
		);

		$this->assertEquals(
			"Bar",
			$book->request->headers->{"X-Foo"}[0]
		);
	}

	public function test_find_with_failed_connection_throws_response_exception(): void
	{
		ConnectionManager::init(
			connections: ["default" => new Connection("https://api.example.com")],
			httpClient: new Shuttle(
				handler: new MockHandler([
					function(Request $request): Response {
						throw new RequestException($request, "Failed to connect.");
					}
				])
			)
		);

		$this->expectException(ResponseException::class);
		Book::find(123);
	}

	public function test_find_with_failed_request_throws_response_exception(): void
	{
		ConnectionManager::init(
			connections: ["default" => new Connection("https://api.example.com")],
			httpClient: new Shuttle(
				handler: new MockHandler([
					function(): Response {
						return new Response(500);
					}
				])
			)
		);

		$this->expectException(ResponseException::class);
		Book::find(123);
	}

	public function test_all(): void
	{
		ConnectionManager::init(
			connections: ["default" => new Connection("https://api.example.com")],
			httpClient: new Shuttle(
				handler: new MockHandler([
					function(Request $request): Response {
						$books = [
							[
								"id" => "6af9a9f5-0251-4e09-be1d-4e36ecb242fa",
								"request" => [
									"method" => $request->getMethod(),
									"uri" => (string) $request->getUri(),
									"headers" => $request->getHeaders(),
								]
							]
						];

						return new Response(ResponseStatus::OK, \json_encode($books));
					}
				])
			)
		);

		$books = Book::all(["p" => 1], ["X-Foo" => "Bar"]);

		$this->assertCount(1, $books);

		$this->assertEquals(
			"books/6af9a9f5-0251-4e09-be1d-4e36ecb242fa",
			$books[0]->getResourceUri()
		);

		$this->assertEquals(
			"GET",
			$books[0]->request->method
		);

		$this->assertEquals(
			"https://api.example.com/books?p=1",
			$books[0]->request->uri,
		);

		$this->assertEquals(
			"Bar",
			$books[0]->request->headers->{"X-Foo"}[0]
		);
	}

	public function test_all_with_failed_connection_throws_response_exception(): void
	{
		ConnectionManager::init(
			connections: ["default" => new Connection("https://api.example.com")],
			httpClient: new Shuttle(
				handler: new MockHandler([
					function(Request $request): Response {
						throw new RequestException($request, "Failed to connect.");
					}
				])
			)
		);

		$this->expectException(ResponseException::class);
		Book::all();
	}

	public function test_all_with_failed_request_throws_response_exception(): void
	{
		ConnectionManager::init(
			connections: ["default" => new Connection("https://api.example.com")],
			httpClient: new Shuttle(
				handler: new MockHandler([
					function(): Response {
						return new Response(500);
					}
				])
			)
		);

		$this->expectException(ResponseException::class);
		Book::all();
	}

	public function test_save_create(): void
	{
		ConnectionManager::init(
			connections: ["default" => new Connection("https://api.example.com")],
			httpClient: new Shuttle(
				handler: new MockHandler([
					function(Request $request): Response {
						$book = [
							"id" => "6af9a9f5-0251-4e09-be1d-4e36ecb242fa",
							"isbn" => "123456",
							"request" => [
								"method" => $request->getMethod(),
								"uri" => (string) $request->getUri(),
								"headers" => $request->getHeaders(),
							],

							"body" => \json_decode($request->getBody()->getContents())
						];

						return new Response(ResponseStatus::CREATED, \json_encode($book));
					}
				])
			)
		);

		$book = new Book([
			"title" => "Do Androids Dream of Electric Sheep?",
			"author" => "Philip K. Dick",
			"publisher" => "Penguin",
			"status" => "in stock",
		]);

		$book->save(["p" => 1], ["X-Foo" => "Bar"]);

		$this->assertEquals(
			[
				"title" => "Do Androids Dream of Electric Sheep?",
				"author" => "Philip K. Dick",
				"publisher" => "Penguin",
				"status" => "in stock",
			],
			(array) $book->body
		);

		$this->assertEquals(
			"6af9a9f5-0251-4e09-be1d-4e36ecb242fa",
			$book->id
		);

		$this->assertEquals(
			"books/6af9a9f5-0251-4e09-be1d-4e36ecb242fa",
			$book->getResourceUri()
		);

		$this->assertEquals(
			"123456",
			$book->isbn
		);

		$this->assertEquals(
			"POST",
			$book->request->method,
		);

		$this->assertEquals(
			"https://api.example.com/books?p=1",
			$book->request->uri,
		);

		$this->assertEquals(
			"Bar",
			$book->request->headers->{"X-Foo"}[0]
		);

		$this->assertEmpty($book->getModifiedProperties());
	}

	public function test_save_triggers_events(): void
	{
		$events = [];

		$dispatcher = new Dispatcher;

		$dispatcher->listen(
			ResourceSavingEvent::class,
			function(ResourceSavingEvent $event) use (&$events): void {
				$events[] = $event;
			}
		);

		$dispatcher->listen(
			ResourceSavedEvent::class,
			function(ResourceSavedEvent $event) use (&$events): void {
				$events[] = $event;
			}
		);

		ConnectionManager::init(
			connections: ["default" => new Connection("https://api.example.com")],
			httpClient: new Shuttle(
				handler: new MockHandler([
					function(Request $request): Response {
						return new Response(
							ResponseStatus::OK,
							\json_encode([
								"id" => "6af9a9f5-0251-4e09-be1d-4e36ecb242fa",
							])
						);
					}
				])
			),
			eventDispatcher: $dispatcher,
		);

		$book = new Book([
			"title" => "Do Androids Dream of Electric Sheep?",
			"author" => "Philip K. Dick",
			"publisher" => "Penguin",
			"status" => "in stock",
		]);

		$book->save();

		$this->assertCount(2, $events);

		$this->assertInstanceOf(
			ResourceSavingEvent::class,
			$events[0]
		);

		$this->assertInstanceOf(
			ResourceSavedEvent::class,
			$events[1]
		);
	}

	public function test_save_update_no_diff(): void
	{
		ConnectionManager::init(
			connections: [
				"default" => new Connection(
					host: "https://api.example.com",
					options: [
						Connection::OPTION_UPDATE_DIFF => false
					]
				)
			],
			httpClient: new Shuttle(
				handler: new MockHandler([
					function(Request $request): Response {
						$book = [
							"id" => "6af9a9f5-0251-4e09-be1d-4e36ecb242fa",
							"isbn" => "123456",
							"request" => [
								"method" => $request->getMethod(),
								"uri" => (string) $request->getUri(),
								"headers" => $request->getHeaders(),
							],

							"body" => \json_decode($request->getBody()->getContents())
						];

						return new Response(ResponseStatus::CREATED, \json_encode($book));
					}
				])
			)
		);

		$book = Book::make([
			"id" => "6af9a9f5-0251-4e09-be1d-4e36ecb242fa",
			"isbn" => "123456",
			"title" => "Do Androids Dream of Electric Sheep?",
			"author" => "Philip K. Dick",
			"publisher" => "Penguin",
			"status" => "in stock",
		]);

		$book->status = "out of stock";

		$book->save(["p" => 1], ["X-Foo" => "Bar"]);

		$this->assertEquals(
			[
				"id" => "6af9a9f5-0251-4e09-be1d-4e36ecb242fa",
				"isbn" => "123456",
				"title" => "Do Androids Dream of Electric Sheep?",
				"author" => "Philip K. Dick",
				"publisher" => "Penguin",
				"status" => "out of stock",
			],
			(array) $book->body
		);

		$this->assertEquals(
			"6af9a9f5-0251-4e09-be1d-4e36ecb242fa",
			$book->id
		);

		$this->assertEquals(
			"books/6af9a9f5-0251-4e09-be1d-4e36ecb242fa",
			$book->getResourceUri()
		);

		$this->assertEquals(
			"123456",
			$book->isbn
		);

		$this->assertEquals(
			"PUT",
			$book->request->method,
		);

		$this->assertEquals(
			"https://api.example.com/books/6af9a9f5-0251-4e09-be1d-4e36ecb242fa?p=1",
			$book->request->uri,
		);

		$this->assertEquals(
			"Bar",
			$book->request->headers->{"X-Foo"}[0]
		);

		$this->assertEmpty($book->getModifiedProperties());
	}

	public function test_save_update_diff(): void
	{
		ConnectionManager::init(
			connections: [
				"default" => new Connection(
					host: "https://api.example.com",
					options: [
						Connection::OPTION_UPDATE_METHOD => "patch",
						Connection::OPTION_UPDATE_DIFF => true,
					]
				)
			],
			httpClient: new Shuttle(
				handler: new MockHandler([
					function(Request $request): Response {
						$book = [
							"id" => "6af9a9f5-0251-4e09-be1d-4e36ecb242fa",
							"isbn" => "123456",
							"request" => [
								"method" => $request->getMethod(),
								"uri" => (string) $request->getUri(),
								"headers" => $request->getHeaders(),
							],

							"body" => \json_decode($request->getBody()->getContents())
						];

						return new Response(ResponseStatus::CREATED, \json_encode($book));
					}
				])
			)
		);

		$book = Book::make([
			"id" => "6af9a9f5-0251-4e09-be1d-4e36ecb242fa",
			"isbn" => "123456",
			"title" => "Do Androids Dream of Electric Sheep?",
			"author" => "Philip K. Dick",
			"publisher" => "Penguin",
			"status" => "in stock",
		]);

		$book->status = "out of stock";

		$book->save(["p" => 1], ["X-Foo" => "Bar"]);

		$this->assertEquals(
			[
				"status" => "out of stock",
			],
			(array) $book->body
		);

		$this->assertEquals(
			"PATCH",
			$book->request->method,
		);

		$this->assertEquals(
			"https://api.example.com/books/6af9a9f5-0251-4e09-be1d-4e36ecb242fa?p=1",
			$book->request->uri,
		);

		$this->assertEquals(
			"Bar",
			$book->request->headers->{"X-Foo"}[0]
		);

		$this->assertEmpty($book->getModifiedProperties());
	}

	public function test_save_excludes_properties(): void
	{
		ConnectionManager::init(
			connections: ["default" => new Connection("https://api.example.com")],
			httpClient: new Shuttle(
				handler: new MockHandler([
					function(Request $request): Response {
						$book = [
							"id" => "6af9a9f5-0251-4e09-be1d-4e36ecb242fa",
							"isbn" => "123456",
							"request" => [
								"method" => $request->getMethod(),
								"uri" => (string) $request->getUri(),
								"headers" => $request->getHeaders(),
							],

							"body" => \json_decode($request->getBody()->getContents())
						];

						return new Response(ResponseStatus::CREATED, \json_encode($book));
					}
				])
			)
		);

		$book = new Book([
			"title" => "Do Androids Dream of Electric Sheep?",
			"author" => "Philip K. Dick",
			"publisher" => "Penguin",
			"status" => "in stock",
			"genre" => "Science Fiction"
		]);

		$book->save();

		$this->assertEquals(
			[
				"title" => "Do Androids Dream of Electric Sheep?",
				"author" => "Philip K. Dick",
				"publisher" => "Penguin",
				"status" => "in stock",
			],
			(array) $book->body
		);
	}

	public function test_delete_no_resource_uri_throws_resource_exception(): void
	{
		$book = new Book;

		$this->expectException(ResourceException::class);
		$book->delete();
	}

	public function test_delete(): void
	{
		$body = [];

		ConnectionManager::init(
			connections: ["default" => new Connection("https://api.example.com")],
			httpClient: new Shuttle(
				handler: new MockHandler([
					function(Request $request) use (&$body): Response {

						$body["method"] = $request->getMethod();
						$body["uri"] = (string) $request->getUri();
						$body["headers"] = $request->getHeaders();

						return new Response(
							ResponseStatus::NO_CONTENT
						);
					}
				])
			),
		);

		$book = Book::make([
			"id" => "6af9a9f5-0251-4e09-be1d-4e36ecb242fa",
			"title" => "Do Androids Dream of Electric Sheep?",
			"author" => "Philip K. Dick",
			"publisher" => "Penguin",
			"status" => "in stock",
		]);

		$book->delete(["p" => 1], ["X-Foo" => "Bar"]);

		$this->assertEquals(
			"DELETE",
			$body["method"]
		);

		$this->assertEquals(
			"https://api.example.com/books/6af9a9f5-0251-4e09-be1d-4e36ecb242fa?p=1",
			$body["uri"]
		);

		$this->assertEquals(
			$body["headers"]["X-Foo"],
			["Bar"]
		);
	}

	public function test_delete_triggers_events(): void
	{
		$events = [];

		$dispatcher = new Dispatcher;
		$dispatcher->listen(
			"*",
			function(ResourceEventAbstract $event) use (&$events): void {
				$events[] = $event;
			}
		);

		ConnectionManager::init(
			connections: ["default" => new Connection("https://api.example.com")],
			httpClient: new Shuttle(
				handler: new MockHandler([
					function(Request $request): Response {
						return new Response(
							ResponseStatus::NO_CONTENT
						);
					}
				])
			),
			eventDispatcher: $dispatcher,
		);

		$book = Book::make([
			"id" => "6af9a9f5-0251-4e09-be1d-4e36ecb242fa",
			"title" => "Do Androids Dream of Electric Sheep?",
			"author" => "Philip K. Dick",
			"publisher" => "Penguin",
			"status" => "in stock",
		]);

		$book->delete();

		$this->assertCount(2, $events);

		$this->assertInstanceOf(
			ResourceDeletingEvent::class,
			$events[0]
		);

		$this->assertInstanceOf(
			ResourceDeletedEvent::class,
			$events[1]
		);
	}
}