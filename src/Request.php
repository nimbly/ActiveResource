<?php
/**
 * Created by PhpStorm.
 * User: brent
 * Date: 1/26/17
 * Time: 2:32 PM
 */

namespace ActiveResource;


class Request
{
    protected $method;
    protected $url;
    protected $headers;
    protected $body;

    public function __construct($method, $url, $headers, $body)
    {
        $this->method = $method;
        $this->url = $url;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function newPsr7Request()
    {
        return new \GuzzleHttp\Psr7\Request($this->method, $this->url, $this->headers, $this->body);
    }
}