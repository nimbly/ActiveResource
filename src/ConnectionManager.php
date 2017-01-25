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
    protected static $connections = [];

    /**
     * Add a connection
     * @param $name
     * @param Connection $connection
     */
    public static function add($name, Connection $connection){
        self::$connections[$name] = $connection;
    }

    /**
     * Get a connection
     * @param $name
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