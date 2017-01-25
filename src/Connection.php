<?php
/**
 * Created by PhpStorm.
 * User: brent
 * Date: 1/7/17
 * Time: 10:19 AM
 */

namespace ActiveResource;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;

class Connection
{
    /** @var Client */
    protected $httpClient = null;

    /** @var array  */
    protected $middlewareInstances = [];

    /** @var array  */
    protected $options = [
        // Base URI will be prepended to each request URI
        'baseUri' => null,

        // The error class to use if the response is not successful
        'errorClass' => null,

        // The response class to use
        'responseClass' => Response::class,

        // The http method to use for updating a resource (usually PUT but some APIs use PATCH)
        'updateMethod' => 'PUT',

        // Array of class names to run as Middleware before each request is sent
        'middleware' => [],
    ];

    /**
     * Current request instance
     *
     * @var Request
     */
    public $request;

    /**
     * Connection constructor.
     * @param Client $httpClient
     * @param array $options
     */
    public function __construct(array $options = [], Client $httpClient = null)
    {
        if( $options ){
            foreach( $options as $option => $value ){
                $this->setOption($option, $value);
            }
        }

        if( empty($httpClient) ){
            $httpClient = new Client;
        }

        $this->setHttpClient($httpClient);
    }

    /**
     * Set an option
     *
     * @param $name
     * @param $value
     *
     * @return Connection
     */
    public function setOption($name, $value)
    {
        if( array_key_exists($name, $this->options) ){
            $this->options[$name] = $value;
        }

        return $this;
    }

    /**
     * Get an option
     *
     * @param $name
     * @return mixed|null
     */
    public function getOption($name)
    {
        if( array_key_exists($name, $this->options) ){
            return $this->options[$name];
        }

        return null;
    }

    /**
     * Set the HTTP client for this connection
     *
     * @param Client $httpClient
     */
    public function setHttpClient(Client $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * @param $method
     * @param $url
     * @param null $queryParams
     * @param null $body
     * @param array $headers
     * @return Request
     */
    public function buildRequest($method, $url, $queryParams = null, $body = null, array $headers = [])
    {
        $method = strtoupper($method);

        // Apply query parameters to URI
        if( !empty($queryParams) ) {
            foreach( $queryParams as &$q ){
                if( is_array($q) ) {
                    $q = implode(',', array_values($q));
                }
            }

            if( ($query = http_build_query($queryParams, null, '&', PHP_QUERY_RFC1738)) ){
                $url .= "?{$query}";
            }
        }

        // Process the body
        if( $body ){
            if( is_array($body) ){
                $body = json_encode($body);
            }
        }

        // Prepend base URI
        $url = $this->getOption('baseUri') . $url;


        return new Request($method, $url, $headers, $body);
    }

    /**
     * Send a request
     *
     * @param Request $request
     * @return mixed|ResponseInterface
     */
    public function send(Request $request)
    {
        return $this->httpClient->send($request);
    }

    /**
     * @param Request $request
     * @return ResponseAbstract
     */
    protected function call(Request $request)
    {
        try {
            $response = $this->send($request);
        } catch( BadResponseException $badResponseException ){
            $response = $badResponseException->getResponse();
        }

        /** @var ResponseAbstract $response */
        $responseClass = $this->getResponseClass();
        $response = new $responseClass($response);

        return $response;
    }

    /**
     * @param $url
     * @param array $queryParams
     * @param array $headers
     * @return ResponseAbstract
     */
    public function get($url, array $queryParams = [], array $headers = [])
    {
        $this->request = $this->buildRequest('GET', $url, $queryParams, null, $headers);
        $this->runMiddleware();
        return $this->call($this->request);
    }

    /**
     * @param $url
     * @param array $queryParams
     * @param null $body
     * @param array $headers
     * @return ResponseAbstract
     */
    public function post($url, array $queryParams = [], $body = null, array $headers = [])
    {
        $this->request = $this->buildRequest('POST', $url, $queryParams, $body, $headers);
        $this->runMiddleware();
        return $this->call($this->request);
    }

    /**
     * @param $url
     * @param array $queryParams
     * @param null $body
     * @param array $headers
     * @return ResponseAbstract
     */
    public function put($url, array $queryParams = [], $body = null, array $headers = [])
    {
        $this->request = $this->buildRequest('PUT', $url, $queryParams, $body, $headers);
        $this->runMiddleware();
        return $this->call($this->request);
    }

    /**
     * @param $url
     * @param array $queryParams
     * @param null $body
     * @param array $headers
     * @return ResponseAbstract
     */
    public function patch($url, array $queryParams = [], $body = null, array $headers = [])
    {
        $this->request = $this->buildRequest('PATCH', $url, $queryParams, $body, $headers);
        $this->runMiddleware();
        return $this->call($this->request);
    }

    /**
     * @param $url
     * @param array $queryParams
     * @param array $headers
     * @return ResponseAbstract
     */
    public function delete($url, array $queryParams = [], array $headers = [])
    {
        $this->request = $this->buildRequest('DELETE', $url, $queryParams, $headers);
        $this->runMiddleware();
        return $this->call($this->request);
    }

    /**
     * @param $url
     * @param array $queryParams
     * @param array $headers
     * @return ResponseAbstract
     */
    public function head($url, array $queryParams = [], array $headers = [])
    {
        $this->request = $this->buildRequest('HEAD', $url, $queryParams, $headers);
        $this->runMiddleware();
        return $this->call($this->request);
    }

    /**
     * @param $url
     * @param array $queryParams
     * @param array $headers
     * @return ResponseAbstract
     */
    public function options($url, array $queryParams = [], array $headers = [])
    {
        $this->request = $this->buildRequest('OPTIONS', $url, $queryParams, $headers);
        $this->runMiddleware();
        return $this->call($this->request);
    }

    /**
     * @return string
     */
    public function getResponseClass()
    {
        return $this->getOption('responseClass');
    }

    /**
     * @return string
     */
    public function getErrorClass()
    {
        return $this->getOption('errorClass');
    }

    /**
     * @return string
     */
    public function getUpdateMethod()
    {
        $method = $this->getOption('updateMethod');

        if( empty($method) ){
            return 'put';
        }

        return strtolower($method);
    }

    /**
     * Loop through list of middleware and run each one
     */
    protected function runMiddleware()
    {
        foreach( $this->getOption('middleware') as $middleware )
        {
            $instance = $this->getMiddlewareInstance($middleware);
            $instance->run($this);
        }
    }

    /**
     * @param $middleware
     * @return mixed
     */
    protected function getMiddlewareInstance($middleware)
    {
        if( isset($this->middlewareInstances[$middleware]) ){
            return $this->middlewareInstances[$middleware];
        }

        $instance = new $middleware;
        $this->middlewareInstances[$middleware] = $instance;
        return $instance;
    }
}