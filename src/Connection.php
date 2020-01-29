<?php

namespace ActiveResource;

use Capsule\Request;
use Capsule\Uri;
use Closure;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Connection
{
    /**
     * PSR-18 HTTP client instance.
     *
     * @var ClientInterface
     */
    protected $httpClient;

    /**
     * Middleware manager.
     *
     * @var ?callable
     */
    protected $middlewarePipeline;

    /**
     * Request/response log.
     *
     * @var array<Log>
     */
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
     * Array of Middleware class names (not instances) to apply before each request is sent.
     *
     * Type: array
     * Default: []
     */
    const OPTION_MIDDLEWARE = 'middleware';

    /**
     * Keep a log of all calls made.
     *
     * Type: boolean
     * Default: false
     */
    const OPTION_LOG = 'log';

    /**
     * HTTP protocol version to use for all calls.
     * 
     * Type: string
     * Default: 1.1
     */
    const OPTION_HTTP_VERSION = 'httpVersion';

    /**
     * 
     * Throw exception on 4xx response codes.
     * 
     * Type: boolean
     * Default: false
     * 
     */
    const OPTION_THROW_4xx = 'throw4xx';

    /**
     * Throw exception on 5xx response codes.
     * 
     * Type: boolean
     * Default: false
     */
    const OPTION_THROW_5xx = 'throw5xx';

	/**
	 * Common API content types
	 */
	const CONTENT_TYPE_JSON = 'application/json';
	const CONTENT_TYPE_XML = 'application/xml';
	const CONTENT_TYPE_FORM = 'application/x-www-form-urlencoded';

    /**
     * Connection options.
     *
     * @var array<string, mixed>
     */
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
        self::OPTION_HTTP_VERSION => '1.1',
        self::OPTION_THROW_4xx => false,
        self::OPTION_THROW_5xx => false
    ];

	/**
     * Last Request object sent.
     *
     * @var RequestInterface
     */
	protected $request;

	/**
     * Last Response object received.
     *
     * @var ResponseInterface
     */
	protected $response;

    /**
     * Connection constructor.
     * 
     * @param ClientInterface $httpClient
     * @param array<string, mixed> $options
     */
    public function __construct(ClientInterface $httpClient, array $options = [])
    {
        $this->httpClient = $httpClient;

        if( $options ){
            foreach( $options as $option => $value ){
                $this->setOption($option, $value);
            }
        }
    }

    /**
     * Set an option
     *
     * @param string $name
     * @param mixed $value
     * @return Connection
     */
    public function setOption(string $name, $value): Connection
    {
        if( \array_key_exists($name, $this->options) ){
            $this->options[$name] = $value;
        }

        return $this;
    }

    /**
     * Get a connection option value.
     *
     * @param string $name
     * @return mixed|null
     */
    public function getOption(string $name)
    {
        return $this->options[$name] ?? null;
    }

    /**
     * Does connection have given option set?
     *
     * @param string $name
     * @return boolean
     */
    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    /**
     * Set this connection to use the Basic authorization scheme
     * by providing the username and password.
     *
     * @param ?string $username
     * @param ?string $password
     * @return Connection
     */
    public function useBasicAuthorization(?string $username, ?string $password): Connection
    {
        $this->options[self::OPTION_DEFAULT_HEADERS] = \array_merge(
            $this->options[self::OPTION_DEFAULT_HEADERS],
            ['Authorization' => 'Basic ' . \base64_encode("{$username}:{$password}")]
        );

        return $this;
    }

    /**
     * Set this connection to use the Bearer authorization scheme
     * by providing the bearer token.
     *
     * @param string $token
     * @return Connection
     */
    public function useBearerAuthorization(string $token): Connection
    {
        $this->options[self::OPTION_DEFAULT_HEADERS] = \array_merge(
            $this->options[self::OPTION_DEFAULT_HEADERS],
            ['Authorization' => "Bearer {$token}"]
        );

        return $this;
    }

    /**
     * Builds a PSR-7 Request instance.
     *
     * @param string $method
     * @param string $uri
     * @param array<string, string>|null $queryParams
     * @param string|null $body
     * @param array<string, string>|null $headers
     * @return RequestInterface
     */
    public function buildRequest(string $method, string $uri, ?array $queryParams, ?string $body, ?array $headers): RequestInterface
    {
        $uri = new Uri(
            $this->getOption(self::OPTION_BASE_URI) . $uri
        );

        $uri = $uri->withQuery(
            \http_build_query(
                \array_merge($this->getOption(self::OPTION_DEFAULT_QUERY_PARAMS) ?? [], $queryParams ?? [])
            )
        );

        $request = new Request(
            $method,
            $uri,
            $body,
            \array_merge($this->getOption(self::OPTION_DEFAULT_HEADERS) ?? [], $headers ?? []),
            $this->getOption(self::OPTION_HTTP_VERSION)
        );

        if( \in_array($request->getMethod(), ['POST','PUT','PATCH']) &&
            $request->hasHeader('Content-Type') === false &&
            $this->hasOption(self::OPTION_DEFAULT_CONTENT_TYPE) ){
            $request = $request->withHeader(
                'Content-Type',
                $this->getOption(self::OPTION_DEFAULT_CONTENT_TYPE)
            );
        }

        return $request;
    }

    /**
     * Make the HTTP call
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function send(RequestInterface $request): ResponseInterface
    {
        // Get the response class name to instantiate (to pass into Middleware)
        $responseClass = $this->getOption(self::OPTION_RESPONSE_CLASS);

        // Save the request object so it may be retrieved
        $this->request = $request;
        
        // Capture start time (for logging requests)
        $start = \microtime(true);

		// Run the request
        $response = $this->run(
            $request,
            function(RequestInterface $request) use ($responseClass): ResponseInterface {

                return $this->httpClient->sendRequest($request);

            }
        );

        // Capture end time
        $stop = microtime(true);

        // Save the response object so it may be retrieved
		$this->response = $response;

        // Should we log this request?
        if( $this->getOption(self::OPTION_LOG) ){
            $this->addLog($request, $response, (int) \round(($stop-$start) * 1000));
        }

        if( $this->shouldThrow($response) ){
            throw new ResponseException($response);
        }

        return $response;
    }

    /**
     * Run the request through the Middleware pipeline.
     *
     * @param RequestInterface $request
     * @param callable $kernel
     * @return ResponseInterface
     */
    private function run(RequestInterface $request, callable $kernel): ResponseInterface
    {
        if( empty($this->middlewarePipeline) ){
            $this->middlewarePipeline = $this->compileMiddleware(
                $this->getOption(self::OPTION_MIDDLEWARE),
                $kernel
            );
        }

        return \call_user_func($this->middlewarePipeline, $request);
    }

    /**
     * Compile the middleware pipeline.
     *
     * @param array<MiddlewareInterface|string> $middleware
     * @return Closure
     */
    private function compileMiddleware(array $middleware, callable $kernel): Closure
    {
        $middlewareStack = [];
        foreach( \array_reverse($middleware) as $layer ){
            $middlewareStack[] = new $layer;
        }

        return \array_reduce($middlewareStack, function(callable $next, object $middleware): Closure {

            return function(RequestInterface $request) use ($next, $middleware): ResponseInterface {
                return $middleware->handle($request, $next);
            };

        }, function(RequestInterface $request) use ($kernel): ResponseInterface {
            return $kernel($request);
        });
    }

    /**
     * Should an exception be thrown for the given Response.
     *
     * @param ResponseInterface $response
     * @return boolean
     */
    private function shouldThrow(ResponseInterface $response): bool
    {
        if( ($response->getStatusCode() >= 400 && $response->getStatusCode() < 500 &&
            $this->getOption(self::OPTION_THROW_4xx)) ||
            
            ($response->getStatusCode() >= 500 &&
            $this->getOption(self::OPTION_THROW_5xx)) ){
            return true;
        }

        return false;
    }

    /**
     * Add an entry into the Request/Response log.
     * 
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param int $time
     * @return void
     */
    private function addLog(RequestInterface $request, ResponseInterface $response, int $time): void
    {
        $this->log[] = new Log($request, $response, $time);
    }

    /**
     * Get the Request/Response log.
     * 
     * @return array<Log>
     */
    public function getLog(): array
    {
        return $this->log;
    }

	/**
	 * Get the last Request object.
	 *
	 * @return RequestInterface
	 */
    public function getLastRequest(): RequestInterface
	{
		return $this->request;
	}

	/**
	 * Get the last Response object.
	 *
	 * @return ResponseInterface
	 */
	public function getLastResponse(): ResponseInterface
	{
		return $this->response;
	}
}