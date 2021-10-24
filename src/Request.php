<?php

namespace ActiveResource;


use \GuzzleHttp\Psr7\Request as Psr7Request;


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
		if( ($index = $this->findArrayIndex($name, $this->query)) ){
			return $this->query[$index];
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
		if( ($query = http_build_query($this->query, "", '&', PHP_QUERY_RFC1738)) ){
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
		if( ($index = $this->findArrayIndex($name, $this->headers)) ){
			return $this->headers[$index];
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
		if( ($index = $this->findArrayIndex($name, $this->headers)) ){
			$this->headers[$index] = $value;
		}

		else {
			$this->headers[$name] = $value;
		}
	}

	/**
	 * Remove a header from the request
	 *
	 * @param $name
	 * @return boolean
	 */
	public function removeHeader($name)
	{
		if( ($index = $this->findArrayIndex($name, $this->headers)) ){
			unset($this->headers[$index]);
			return true;
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
		if( empty($body) ){
			$body = null;
		}

		$this->body = $body;
	}


	/**
	 * Find an array key (index) case-insensitive
	 *
	 * @param $key
	 * @param array $array
	 * @return string|int|bool
	 */
	protected function findArrayIndex($key, array $array)
	{
		foreach( $array as $k => $v )
		{
			if( strtolower($key) === strtolower($k) ){
				return $k;
			}
		}

		return false;
	}


	/**
	 * Build a PSR-7 Request instance
	 *
	 * @return \GuzzleHttp\Psr7\Request
	 */
	public function newPsr7Request()
	{
		return new Psr7Request(
			$this->method,
			$this->url.$this->getQueryAsString(),
			$this->headers,
			$this->body
		);
	}
}