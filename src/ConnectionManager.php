<?php
/**
 * Created by PhpStorm.
 * User: brent
 * Date: 1/7/17
 * Time: 10:48 AM
 */

namespace ActiveResource;


class ConnectionManager
{
    /**
     * @var Connection[]
     */
    protected static $connections = [];

    /**
     * Add an API connection
     *
     * @param string $name
     * @param Connection $connection
     */
    public static function add($name, Connection $connections){
        self::$connections[$name] = $connections;
    }

    /**
     * Get an API connection by its name
     *
     * @param string $name
     * @throws ActiveResourceException
     * @return Connection
     */
    public static function get($name){

        if( isset(self::$connections[$name]) ){
            return self::$connections[$name];
        }

        throw new ActiveResourceException("Connection \"{$name}\" not found");
    }
}