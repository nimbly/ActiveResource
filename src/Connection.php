<?php

namespace ActiveResource;


use ActiveResource\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use Optimus\Onion\Onion;

class Connection
{
    /** @var Client */
    protected $httpClient = null;

    /** @var  Onion */
    protected $middlewareManager;

    /** @var array  */
    protected $log = [];

    /**
     * The base URI to prepend to each request
     *
     * Type: string
     * Default: null
     */
    const OPTION_BASE_URI = 'baseUri';

    /**
     * An array of key => value pairs to include in the headers with each request.
     *
     * Type: array
     * Default: []
     */
    const OPTION_DEFAULT_HEADERS = 'defaultHeaders';

	/**
	 * The default Content-Type when sending requests with a body (POST, PUT, PATCH)
	 *
	 * Type: string
	 * Default: 'application/json'
	 */
	const OPTION_DEFAULT_CONTENT_TYPE = 'defaultContentType';

    /**
     * An array of key => value pairs to include in the query params with each request.
     *
     * Type: array
     * Default: []
     */
    const OPTION_DEFAULT_QUERY_PARAMS = 'defaultQueryParams';

    /**
     * Response class name
     *
     * Type: string
     * Default: 'ActiveResource\\Response'
     */
    const OPTION_RESPONSE_CLASS = 'responseClass';

	/**
	 * Name of custom Collection class to use to pass array of models to. Class must allow passing in array of data into
	 * constructor. Set to NULL to return a simple Array of objects.
	 *
	 * Type: string
	 * Default: 'ActiveResource\\Collection'
	 */
	const OPTION_COLLECTION_CLASS = 'collectionClass';

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

    /**
     * Keep a log of all calls made
     *
     * Type: boolean
     * Default: false
     */
    const OPTION_LOG = 'log';

	/**
	 * Common API content types
	 */
	const CONTENT_TYPE_JSON = 'application/json';
	const CONTENT_TYPE_XML = 'application/xml';
	const CONTENT_TYPE_FORM = 'application/x-www-form-urlencoded';


    /** @var array  */
    protected $options = [
        self::OPTION_BASE_URI => null,
        self::OPTION_DEFAULT_HEADERS => [],
		self::OPTION_DEFAULT_CONTENT_TYPE => self::CONTENT_TYPE_JSON,
        self::OPTION_DEFAULT_QUERY_PARAMS => [],
        self::OPTION_RESPONSE_CLASS => 'ActiveResource\\Response',
		self::OPTION_COLLECTION_CLASS => 'ActiveResource\\Collection',
        self::OPTION_UPDATE_METHOD => 'put',
        self::OPTION_UPDATE_DIFF => false,
        self::OPTION_MIDDLEWARE => [],
        self::OPTION_LOG => false,
    ];

	/** @var Request */
	protected $request;

	/** @var ResponseAbstract */
	protected $response;

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

        if( !empty($httpClient) ){
            $this->setHttpClient($httpClient);
        }
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
     * Build an ActiveResource Request object instance using the connection's options.
     *
     * This Request object will be passed through the middleware layers.
     *
     * @param string $method
     * @param string $url
     * @param array $queryParams
     * @param mixed|null $body
     * @param array $headers
     * @return Request
     */
    public function buildRequest($method, $url, array $queryParams = [], $body = null, array $headers = [])
    {
        // Normalize method
        $method = strtoupper($method);

        // Prepend base URI
        $url = $this->getOption(self::OPTION_BASE_URI) . $url;

        // Merge in default query params
        $queryParams = array_merge($this->getOption(self::OPTION_DEFAULT_QUERY_PARAMS), $queryParams);

        // If we have an empty/falsey body, set body to null
        if( empty($body) ){
            $body = null;
        }

        // Set the default Content-Type header for request types with a message body
        if( in_array($method, ['POST', 'PUT', 'PATCH']) &&
			($contentType = $this->getOption(self::OPTION_DEFAULT_CONTENT_TYPE)) ){
			$headers['Content-Type'] = $contentType;
		}

        // Merge in default headers
        $headers = array_merge($this->getOption(self::OPTION_DEFAULT_HEADERS), $headers);

        return new Request($method, $url, $queryParams, $headers, $body);
    }

    /**
     * Make the HTTP call
     *
     * @param Request $request
     * @throws ConnectException
     * @return ResponseAbstract
     */
    public function send(Request $request)
    {
        // Initialize middleware manager
        $this->initializeMiddlewareManager();

        // Lazy load the the HttpClient
        if( empty($this->httpClient) ){
            $this->setHttpClient(new Client);
        }

        // Get the response class name to instantiate (to pass into Middleware)
        $responseClass = $this->getOption(self::OPTION_RESPONSE_CLASS);

		// Capture start time (for logging requests)
        $start = microtime(true);

        // Save the request object so it may be retrieved
		$this->request = $request;

		// Run the request
        /** @var ResponseAbstract $response */
        $response = $this->middlewareManager->peel($request, function(Request $request) use ($responseClass) {
            try {
                $response = $this->httpClient->send($request->newPsr7Request());
            } catch( BadResponseException $badResponseException ){
                $response = $badResponseException->getResponse();
            }

            return new $responseClass($response);
        });

        // Capture end time
        $stop = microtime(true);

        // Save the response object so it may be retrieved
		$this->response = $response;

        // Should we log this request?
        if( $this->getOption(self::OPTION_LOG) ){
            $this->addLog($request, $response, ($stop-$start));
        }

        return $response;
    }

    /**
     * @return array
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * @param Request $request
     * @param ResponseAbstract $response
     * @param float $timing
     */
    private function addLog(Request $request, ResponseAbstract $response, $timing)
    {
        $this->log[] = [
            'request' => [
                'method' => $request->getMethod(),
                'url' => $request->getUrl().$request->getQueryAsString(),
                'query' => $request->getQueries(),
                'headers' => $request->getHeaders(),
                'body' => $request->getBody(),
            ],
            'response' => [
                'status' => $response->getStatusCode(),
                'phrase' => $response->getStatusPhrase(),
                'headers' => $response->getHeaders(),
                'body' => $response->getBody(),
            ],
            'time' => $timing,
        ];
    }

    /**
     * Initialize middleware manager by instantiating all middlware classes
     * and creating Onion instance.
     *
     * @return void
     */
    private function initializeMiddlewareManager()
    {
        if( empty($this->middlewareManager) ){

            $layers = [];
            foreach( $this->getOption(self::OPTION_MIDDLEWARE) as $middleware ){
                $layers[] = new $middleware;
            }

            // Create new Onion
            $this->middlewareManager = new Onion($layers);
        }
    }

	/**
	 * Get the last Request object
	 *
	 * @return \ActiveResource\Request
	 */
    public function getLastRequest()
	{
		return $this->request;
	}

	/**
	 * Get the last Response object
	 *
	 * @return ResponseAbstract
	 */
	public function getLastResponse()
	{
		return $this->response;
	}
}