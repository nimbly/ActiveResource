<?php

namespace Tests;


use Capsule\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Shuttle\Handler\MockHandler;
use Shuttle\Shuttle;

class BaseTestCase extends TestCase
{
	/**
	 * Build the HTTP client with a MockerHandler.
	 *
	 * @param array<Response> $responses
	 * @return ClientInterface
	 */
	protected function buildMockClient(array $responses = []): ClientInterface
	{
		return new Shuttle([
			'handler' => new MockHandler($responses)
		]);
	}
}