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
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;

class Connection
{
    /** @var Client */
    protected $httpClient = null;

    /** @var array  */
    protected $middlewareInstances = [];

    /**
     * The base URI to prepend to each request
     *
     * Type: string
     * Default: null
     */
    const OPTION_BASE_URI = 'baseUri';

    /**
     * An array of key => value pairs to include in the query params with each request.
     *
     * Type: array
     * Default: []
     */
    const OPTION_DEFAULT_QUERY_PARAMS = 'defaultQueryParams';

    /**
     * Request body format - either 'json', 'form', or null for pass-through
     *
     * Type: string
     * Options: 'json', 'form', null
     * Default: 'json'
     */
    const OPTION_REQUEST_BODY_FORMAT = 'requestBodyFormat';

    /**
     * Error class name
     *
     * Type: string
     * Default: null
     */
    const OPTION_ERROR_CLASS = 'errorClass';

    /**
     * Response class name
     *
     * Type: string
     * Default: null
     */
    const OPTION_RESPONSE_CLASS = 'responseClass';

    /**
     * HTTP method to use for updates
     *
     * Type: string
     * Default: 'put'
     */
    const OPTION_UPDATE_METHOD = 'updateMethod';

    /**
     * If the API allows you to send *just* the modified fields on update, you can set this to true to help
     * speed things up by making the request body smaller.
     *
     * Type: boolean
     * Default: false
     */
    const OPTION_UPDATE_DIFF = 'updateDiff';

    /**
     * Array of class names to apply before each request is sent.
     *
     * Type: array
     * Default: []
     */
    const OPTION_MIDDLEWARE = 'middleware';

    /** @var array  */
    protected $options = [
        self::OPTION_BASE_URI => null,
        self::OPTION_DEFAULT_QUERY_PARAMS => [],
        self::OPTION_REQUEST_BODY_FORMAT => 'json',
        self::OPTION_ERROR_CLASS => Error::class,
        self::OPTION_RESPONSE_CLASS => Response::class,
        self::OPTION_UPDATE_METHOD => 'put',
        self::OPTION_UPDATE_DIFF => false,
        self::OPTION_MIDDLEWARE => [],
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
     * @param string $method
     * @param string $url
     * @param array $queryParams
     * @param mixed|null $body
     * @param array $headers
     * @return Request
     */
    public function buildRequest($method, $url, array $queryParams = [], $body = null, array $headers = [])
    {
        $method = strtoupper($method);

        if( $queryParams == null ){
            $queryParams = [];
        }

        // Merge in default query params
        $queryParams = array_merge($queryParams, $this->getOption(self::OPTION_DEFAULT_QUERY_PARAMS));

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
            $format = $this->getOption(self::OPTION_REQUEST_BODY_FORMAT);

            if( $format == 'json' ){
                $headers['Content-Type'] = 'application/json';
                $body = json_encode($body);
            }

            if( $format == 'form' ){
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
                $body = http_build_query($body, null, '&');
            }

        }

        // Prepend base URI
        $url = $this->getOption(self::OPTION_BASE_URI) . $url;


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
     *
     * @throws ConnectException
     *
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
     *
     * @throws ConnectException
     *
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
     *
     * @throws ConnectException
     *
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
     *
     * @throws ConnectException
     *
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
     *
     * @throws ConnectException
     *
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
     *
     * @throws ConnectException
     *
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
     *
     * @throws ConnectException
     *
     * @return ResponseAbstract
     */
    public function head($url, array $queryParams = [], array $headers = [])
    {
        $this->request = $this->buildRequest('HEAD', $url, $queryParams, $headers);
        $this->runMiddleware();
        return $this->call($this->request);
    }

    /**
     * @return string
     */
    public function getResponseClass()
    {
        return $this->getOption(self::OPTION_RESPONSE_CLASS);
    }

    /**
     * @return string
     */
    public function getErrorClass()
    {
        return $this->getOption(self::OPTION_ERROR_CLASS);
    }

    /**
     * @return string
     */
    public function getUpdateMethod()
    {
        $method = $this->getOption(self::OPTION_UPDATE_METHOD);

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
        foreach( $this->getOption(self::OPTION_MIDDLEWARE) as $middleware )
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