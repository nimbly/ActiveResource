<?php
/**
 * Created by PhpStorm.
 * User: brent
 * Date: 1/18/17
 * Time: 5:15 PM
 */

namespace ActiveResource;


abstract class ErrorAbstract
{
    protected $payload;

    public function __construct($payload)
    {
        $this->payload = $payload;
    }

    /**
     * Get the error message returned by the API
     *
     * @return string
     */
    abstract public function getMessage();
}