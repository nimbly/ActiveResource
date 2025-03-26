<?php

namespace Nimbly\ActiveResource\Tests;

use UnexpectedValueException;
use PHPUnit\Framework\TestCase;
use Nimbly\ActiveResource\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\BackupStaticProperties;

#[CoversClass(Connection::class)]
#[BackupStaticProperties(true)]
class ConnectionTest extends TestCase
{
	public function test_get_host(): void
	{
		$connection = new Connection("https://api.example.com");

		$this->assertEquals(
			"https://api.example.com",
			$connection->getHost()
		);
	}

	public function test_overriding_default_options(): void
	{
		$connection = new Connection(
			host: "https://api.example.com",
			options: [
				Connection::OPTION_UPDATE_DIFF => true,
				Connection::OPTION_UPDATE_METHOD => "patch",
				Connection::OPTION_HTTP_VERSION => "2.0",
				Connection::OPTION_SERIALIZER => "foo",
				Connection::OPTION_DESERIALIZER => "bar",
			]
		);

		$this->assertTrue(
			$connection->getOption(Connection::OPTION_UPDATE_DIFF)
		);

		$this->assertEquals(
			"patch",
			$connection->getOption(Connection::OPTION_UPDATE_METHOD)
		);

		$this->assertEquals(
			"2.0",
			$connection->getOption(Connection::OPTION_HTTP_VERSION)
		);

		$this->assertEquals(
			"foo",
			$connection->getOption(Connection::OPTION_SERIALIZER)
		);

		$this->assertEquals(
			"bar",
			$connection->getOption(Connection::OPTION_DESERIALIZER)
		);
	}

	public function test_get_headers(): void
	{
		$connection = new Connection(
			host: "https://api.example.com",
			headers: ["Foo" => "Bar"]
		);

		$this->assertEquals(
			["Foo" => "Bar"],
			$connection->getHeaders()
		);
	}

	public function test_get_query(): void
	{
		$connection = new Connection(
			host: "https://api.example.com",
			query: ["foo" => "bar"]
		);

		$this->assertEquals(
			["foo" => "bar"],
			$connection->getQuery()
		);
	}

	public function test_get_option_with_default_values(): void
	{
		$connection = new Connection("https://api.example.com");

		$this->assertEquals(
			"put",
			$connection->getOption(Connection::OPTION_UPDATE_METHOD),
		);

		$this->assertEquals(
			false,
			$connection->getOption(Connection::OPTION_UPDATE_DIFF),
		);

		$this->assertEquals(
			"1.1",
			$connection->getOption(Connection::OPTION_HTTP_VERSION),
		);

		$this->assertEquals(
			"\json_encode",
			$connection->getOption(Connection::OPTION_SERIALIZER),
		);

		$this->assertEquals(
			"\json_decode",
			$connection->getOption(Connection::OPTION_DESERIALIZER),
		);
	}

	public function test_get_unset_option_returns_null(): void
	{
		$connection = new Connection("https://api.example.com");
		$this->assertNull($connection->getOption("foo"));
	}

	public function test_serialize(): void
	{
		$connection = new Connection("https://api.example.com");

		$result = $connection->serialize(["foo" => "bar"]);

		$this->assertEquals(
			"{\"foo\":\"bar\"}",
			$result
		);
	}

	public function test_serialize_with_non_callable_throws_unexpected_value_exception(): void
	{
		$connection = new Connection(
			host: "https://api.example.com",
			options: [
				Connection::OPTION_SERIALIZER => "foo"
			]
		);

		$this->expectException(UnexpectedValueException::class);
		$connection->serialize(["foo" => "bar"]);
	}

	public function test_deserialize(): void
	{
		$connection = new Connection("https://api.example.com");

		$result = $connection->deserialize("{\"foo\":\"bar\"}");

		$this->assertEquals(
			["foo" => "bar"],
			(array) $result
		);
	}

	public function test_deserialize_with_non_callable_throws_unexpected_value_exception(): void
	{
		$connection = new Connection(
			host: "https://api.example.com",
			options: [
				Connection::OPTION_DESERIALIZER => "foo"
			]
		);

		$this->expectException(UnexpectedValueException::class);
		$connection->deserialize("{\"foo\":\"bar\"}");
	}
}