<?php
/**
 * Created by PhpStorm.
 * User: brent
 * Date: 5/18/17
 * Time: 2:09 PM
 */

namespace Tests;


use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\TestCase;

class BaseTestCase extends TestCase
{

	protected function buildMockClient(array $responses)
	{
		return new Client([
			'handler' => HandlerStack::create(new MockHandler($responses))
		]);
	}

}