<?php

namespace ActiveResource;

use Exception;


class ConnectionManager
{
	/**
	 * Connections.
	 *
	 * @var array<string,Connection>
	 */
	protected static $connections = [];

	/**
	 * Add an API connection
	 *
	 * @param Connection $connection
	 * @param string $name
	 */
	public static function add(Connection $connection, string $name = "default"): void
	{
		self::$connections[$name] = $connection;
	}

	/**
	 * Get a connection by its name.
	 *
	 * @param string $name
	 * @throws Exception
	 * @return Connection
	 */
	public static function get(string $name): Connection
	{
		if( isset(self::$connections[$name]) ){
			return self::$connections[$name];
		}

		throw new Exception("Connection \"{$name}\" not found");
	}
}