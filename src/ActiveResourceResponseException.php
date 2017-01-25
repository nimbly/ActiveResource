<?php
/**
 * Created by PhpStorm.
 * User: brent
 * Date: 1/12/17
 * Time: 1:59 PM
 */

namespace ActiveResource;


class ActiveResourceResponseException extends \RuntimeException
{
    protected $error = null;

    public function __construct(ErrorAbstract $error)
    {
        parent::__construct($error->getMessage(), $error->getResponse()->getStatusCode(), null);

        $this->error = $error;
    }

    public function getError()
    {
        return $this->error;
    }
}