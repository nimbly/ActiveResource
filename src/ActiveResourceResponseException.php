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
    protected $response = null;
    protected $error = null;

    public function __construct(ResponseAbstract $response, ErrorAbstract $error)
    {
        parent::__construct($error->getMessage(), $response->getStatusCode(), null);

        $this->response = $response;
        $this->error = $error;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getError()
    {
        return $this->error;
    }
}