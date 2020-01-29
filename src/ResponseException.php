<?php

namespace ActiveResource;

use Exception;
use Psr\Http\Message\ResponseInterface;


class ResponseException extends Exception
{
    /**
     * Response instance.
     *
     * @var ResponseInterface
     */
    protected $response;

    /**
     * ResponseException constructor.
     *
     * @param ResponseInterface $response
     */
    public function __construct(ResponseInterface $response)
    {
        parent::__construct($response->getReasonPhrase(), $response->getStatusCode());
        $this->response = $response;
    }

    /**
     * Get the Response instance.
     *
     * @return ResponseInterface
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}