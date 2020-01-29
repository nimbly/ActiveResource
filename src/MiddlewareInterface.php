<?php

namespace ActiveResource;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;


interface MiddlewareInterface
{
    /**
     * Middleware layer handler.
     *
     * @param RequestInterface $request
     * @param callable $next
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface;
}