<?php

namespace ActiveResource;

class Response
{
    public function __construct(ResponseInterface $response)
    {
        
    }

    public function isSuccessful(): bool
    {
        return $this->response->getStatusCode() < 400;
    }
}