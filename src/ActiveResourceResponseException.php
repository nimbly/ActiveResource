<?php

namespace ActiveResource;


class ActiveResourceResponseException extends \RuntimeException
{
    protected $response = null;

    public function __construct(ResponseAbstract $response)
    {
        parent::__construct($response->getStatusPhrase(), $response->getStatusCode(), null);

        $this->response = $response;
    }

    public function getResponse()
    {
        return $this->response;
    }
}