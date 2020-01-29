<?php
/**
 * Created by PhpStorm.
 * User: brent
 * Date: 5/18/17
 * Time: 2:07 PM
 */

namespace Tests;


use ActiveResource\Connection;
use ActiveResource\ConnectionManager;
use ActiveResource\Request;
use ActiveResource\ResponseAbstract;
use GuzzleHttp\Psr7\Response;
use Shuttle\Handler\MockHandler;
use Shuttle\Shuttle;
use Tests\Models\Blogs;
use Tests\Models\CustomResponseClass;
use Tests\Models\Users;

class ConnectionOptionsTest extends BaseTestCase
{

	public function test_option_default_uri()
	{
		$baseUri = 'http://api.nimbly.io/v1/';

		$connection = new Connection(
			$this->buildMockClient(),
			[
				Connection::OPTION_BASE_URI => $baseUri,
			]
		);

		$request = $connection->buildRequest('get', 'test/1');

		$this->assertEquals($request->getUri(), $baseUri.'test/1');
	}

	public function test_option_default_headers()
	{
		$connection = new Connection(
			$this->buildMockClient(),
			[
				Connection::OPTION_DEFAULT_HEADERS => [
					'X-Custom-Header' => 'Foo',
				],

				Connection::OPTION_BASE_URI => 'http://api.nimbly.io/v1/'
			]
		);

		$request = $connection->buildRequest('get', 'test/1');

		$this->assertEquals('Foo', $request->getHeader('X-Custom-Header'));
	}

	public function test_option_default_query_params()
	{
		$connection = new Connection(
			$this->buildMockClient(),
			[
				Connection::OPTION_DEFAULT_QUERY_PARAMS => [
					'username' => 'foo',
					'key' => 'bar',
				],
				Connection::OPTION_BASE_URI => 'http://api.nimbly.io/v1/'
			]
		);

		$request = $connection->buildRequest('post', 'test/1');

		$this->assertEquals('foo', $request->getQuery('username'));
		$this->assertEquals('bar', $request->getQuery('key'));
	}

	public function test_option_default_content_type()
	{
		$connection = new Connection(
			$this->buildMockClient(),
			[
				Connection::OPTION_DEFAULT_CONTENT_TYPE => Connection::CONTENT_TYPE_XML,
				Connection::OPTION_BASE_URI => 'http://api.nimbly.io/v1/'
			]
		);

		$request = $connection->buildRequest('post', 'test', [], 'foo');

		$this->assertEquals(Connection::CONTENT_TYPE_XML, $request->getHeader('Content-Type'));
	}

	public function test_option_response_class()
	{
	    ConnectionManager::add(
			new Connection(
				$this->buildMockClient([
					new Response(200)
				]),

				[
					Connection::OPTION_RESPONSE_CLASS => '\\Tests\\Models\\CustomResponseClass',
					Connection::OPTION_BASE_URI => 'http://api.nimbly.io/v1/'
				]
			)
		);

	    $response = Users::find(1);

	    $this->assertTrue($response->getResponse() instanceof CustomResponseClass);
	}

	public function test_option_log()
	{
		ConnectionManager::add(
			new Connection(
				$this->buildMockClient([
					new Response(200)
				]),
				[
					Connection::OPTION_LOG => true,
					Connection::OPTION_BASE_URI => 'http://api.nimbly.io/v1/'
				]
			)
		);

		$blogs = Blogs::find(1);

		$log = $blogs->getConnection()->getLog();

		$this->assertTrue($log[0]['request'] instanceof Request, 'Log request not instance of Request');
		$this->assertTrue($log[0]['response'] instanceof ResponseAbstract, 'Log response not instance of ResponseAbstract');
		$this->assertNotEmpty($log[0]['time'], 'Log timing is empty');
	}
}