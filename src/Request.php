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
    /** @var  string */
    protected $method;

    /** @var  string */
    protected $url;

    /** @var array  */
    protected $query = [];

    /** @var  array */
    protected $headers = [];

    /** @var  string */
    protected $body;

    /**
     * Build an ActiveResource request object
     *
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE, HEAD)
     * @param string $url Fully qualified URL (https://some.api.com/v1/posts/456)
     * @param array $query Associative array of query params to add to URL
     * @param array $headers Associative array of headers to add to request
     * @param string $body Request body
     */
    public function __construct($method = null, $url = null, $query = [], $headers = [], $body = null)
    {
        $this->method = $method;
        $this->url = $url;
        $this->query = $query;
        $this->headers = $headers;
        $this->body = $body;
    }

    /**
     * Get HTTP method for request
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Set the HTTP method for the request
     *
     * @param string $method
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * Get the URL for the request
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set the URL for the request
     *
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Get the URL query for the request
     *
     * @return array
     */
    public function getQueries()
    {
        return $this->query;
    }

    /**
     * Set an array of queries
     *
     * @param array $queries
     */
    public function setQueries(array $queries)
    {
        $this->query = $queries;
    }

    /**
     * Get a query param from the request
     *
     * @param $name
     * @return mixed|null
     */
    public function getQuery($name)
    {
        foreach( $this->query as $query => $value )
        {
            if( strtolower($name) == strtolower($query) ){
                return $value;
            }
        }

        return null;
    }

    /**
     * Add a query parameter
     *
     * @param $name
     * @param $value
     */
    public function setQuery($name, $value)
    {
        $this->query[$name] = $value;
    }

    /**
     * Return query as HTTP query string (RFC1738)
     *
     * @return null|string
     */
    public function getQueryAsString()
    {
        // Process the query array
        if( ($query = http_build_query($this->query, null, '&', PHP_QUERY_RFC1738)) ){
            return "?{$query}";
        }

        return null;
    }


    /**
     * Get the headers for the request
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Set an array of headers for the request
     *
     * @param array $headers
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }

    /**
     * Get a header from the request
     *
     * @param $name
     * @return string|null
     */
    public function getHeader($name)
    {
        foreach( $this->headers as $header => $value )
        {
            if( strtolower($name) == strtolower($header) ){
                return $value;
            }
        }

        return null;
    }

    /**
     * Set a header for the request
     *
     * @param string $name
     * @param string $value
     */
    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;
    }

    /**
     * Remove a header from the request
     *
     * @param $name
     * @return boolean
     */
    public function removeHeader($name)
    {
        foreach( $this->headers as $header => $value )
        {
            if( strtolower($name) == strtolower($header) ){
                unset($this->headers[$header]);
                return true;
            }
        }

        return false;
    }

    /**
     * Get the request body
     *
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Set the request body
     *
     * @param string $body
     */
    public function setBody($body)
    {
        $this->body = $body;
    }


    /**
     * Build a PSR-7 Request instance
     *
     * @return \GuzzleHttp\Psr7\Request
     */
    public function newPsr7Request()
    {
        return new \GuzzleHttp\Psr7\Request($this->method, $this->url.$this->getQueryAsString(), $this->headers, $this->body);
    }
}