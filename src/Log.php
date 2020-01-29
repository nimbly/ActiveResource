<?php

namespace ActiveResource;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Log
{
    /**
     * Request instance.
     *
     * @var RequestInterface
     */
    protected $request;

    /**
     * Response instance.
     *
     * @var ResponseInterface
     */
    protected $response;

    /**
     * Request time, in milliseconds.
     *
     * @var int
     */
    protected $time;

    /**
     * Log contructor.
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param int $time
     */
    public function __construct(RequestInterface $request, ResponseInterface $response, int $time)
    {
        $this->request = $request;
        $this->response = $response;
        $this->time = $time;
    }

    /**
     * Get the Request.
     *
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Get the Response.
     *
     * @return ResponseInterface
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Get the response time, in milliseconds.
     *
     * @return float
     */
    public function getTime(): float
    {
        return $this->time;
    }
}