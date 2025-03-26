<?php

namespace Nimbly\ActiveResource\Tests;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use Nimbly\ActiveResource\ConnectionManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\BackupStaticProperties;
use ReflectionClass;

#[CoversClass(ConnectionManager::class)]
#[BackupStaticProperties(true)]
class ConnectionManagerTest extends TestCase
{
	public function test_get_instance_on_non_initialized_throws_runtime_exception(): void
	{
		$reflectionClass = new ReflectionClass(ConnectionManager::class);
		$reflectionClass->setStaticPropertyValue("instance", null);


		$this->expectException(RuntimeException::class);
		ConnectionManager::getInstance();
	}
}