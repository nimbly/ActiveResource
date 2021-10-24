<?php

namespace ActiveResource;


class ConnectionManager
{
	/**
	 * @var array<Connection>
	 */
	protected static $connections = [];

	/**
	 * Add an API connection
	 *
	 * @param string $name
	 * @param Connection $connection
	 * @return void
	 */
	public static function add($name, Connection $connection): void
	{
		self::$connections[$name] = $connection;
	}

	/**
	 * Get an API connection by its name
	 *
	 * @param string $name
	 * @throws ActiveResourceException
	 * @return Connection
	 */
	public static function get($name): Connection
	{
		if( isset(self::$connections[$name]) ){
			return self::$connections[$name];
		}

		throw new ActiveResourceException("Connection \"{$name}\" not found");
	}
}