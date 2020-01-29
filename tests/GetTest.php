<?php

namespace Tests;

use ActiveResource\Collection;
use ActiveResource\Connection;
use ActiveResource\ConnectionManager;
use Capsule\Response;
use Tests\Models\Blogs;
use Tests\Models\Users;

class GetTest extends BaseTestCase
{
	public function test_get()
	{
		$responses = [
			new Response(200, json_encode([
				'id' => 7,
				'title' => 'Blog Post',
				'created_at' => '2017-01-28 03:45:00',
				'author' => [
					'id' => 40,
					'name' => 'Brent Scheffler',
					'email' => 'brent@nimbly.io',
				],
				'comments' => [
					[
						'id' => 1,
						'body' => 'Comment 1',
						'created_at' => '2017-01-29 12:34:00',
						'author' => [
							'id' => 3,
							'name' => 'Joe Blow',
							'email' => 'jblow@example.com',
						]
					],

					[
						'id' => 2,
						'body' => 'Comment 2',
						'created_at' => '2017-02-04 16:12:33',
						'author' => [
							'id' => 5,
							'name' => 'Jane Doe',
							'email' => 'jdoe@example.com',
						]
					],
				]
			])),
		];

		ConnectionManager::add(
			new Connection(
				$this->buildMockClient($responses),
				[
					Connection::OPTION_BASE_URI => 'http://api.nimbly.io/v1/',
				]
			)
		);

		$blog = Blogs::find(1);

		$this->assertTrue($blog instanceof Blogs);
		$this->assertTrue($blog->author instanceof Users);
		$this->assertTrue($blog->comments instanceof Collection);
		$this->assertCount(2, $blog->comments);
	}
}