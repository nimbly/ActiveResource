<?php

namespace ActiveResource;

use Capsule\Factory\RequestFactory;
use Capsule\Factory\ResponseFactory;
use Capsule\Factory\StreamFactory;
use Closure;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Shuttle\Shuttle;
use UnexpectedValueException;

class Connection
{
	protected ClientInterface $httpClient;
	protected RequestFactoryInterface $requestFactory;
	protected StreamFactoryInterface $streamFactory;
	protected ResponseFactoryInterface $responseFactory;

	/**
	 * Compiled middleware pipeline.
	 *
	 * @var callable|null
	 */
	protected $middlewarePipeline;

	/**
	 * @var array
	 */
	protected $log = [];

	/**
	 * The base URI to prepend to each request
	 *
	 * Type: string
	 * Default: null
	 */
	const OPTION_BASE_URI = "baseUri";

	/**
	 * An array of key => value pairs to include in the headers with each request.
	 *
	 * Type: array
	 * Default: []
	 */
	const OPTION_DEFAULT_HEADERS = "defaultHeaders";

	/**
	 * The default Content-Type when sending requests with a body (POST, PUT, PATCH)
	 *
	 * Type: string
	 * Default: "application/json"
	 */
	const OPTION_DEFAULT_CONTENT_TYPE = "defaultContentType";

	/**
	 * An array of key => value pairs to include in the query params with each request.
	 *
	 * Type: array
	 * Default: []
	 */
	const OPTION_DEFAULT_QUERY_PARAMS = "defaultQueryParams";

	/**
	 * Response class name
	 *
	 * Type: string
	 * Default: "ActiveResource\\Response"
	 */
	const OPTION_RESPONSE_CLASS = "responseClass";

	/**
	 * Name of custom Collection class to use to pass array of models to. Class must allow passing in array of data into
	 * constructor. Set to NULL to return a simple Array of objects.
	 *
	 * Type: string
	 * Default: "ActiveResource\\Collection"
	 */
	const OPTION_COLLECTION_CLASS = "collectionClass";

	/**
	 * HTTP method to use for updates
	 *
	 * Type: string
	 * Default: "put"
	 */
	const OPTION_UPDATE_METHOD = "updateMethod";

	/**
	 * If the API allows you to send *just* the modified fields on update, you can set this to true to help
	 * speed things up by making the request body smaller.
	 *
	 * Type: boolean
	 * Default: false
	 */
	const OPTION_UPDATE_DIFF = "updateDiff";

	/**
	 * Array of Middleware class names (not instances) to apply before each request is sent.
	 *
	 * Type: array
	 * Default: []
	 */
	const OPTION_MIDDLEWARE = "middleware";

	/**
	 * Keep a log of all calls made.
	 *
	 * Type: boolean
	 * Default: false
	 */
	const OPTION_LOG = "log";

	/**
	 * HTTP protocol version to use for all calls.
	 *
	 * Type: string
	 * Default: 1.1
	 */
	const OPTION_HTTP_VERSION = "httpVersion";

	/**
	 *
	 * Throw exception on 4xx response codes.
	 *
	 * Type: boolean
	 * Default: false
	 *
	 */
	const OPTION_THROW_4xx = "throw4xx";

	/**
	 * Throw exception on 5xx response codes.
	 *
	 * Type: boolean
	 * Default: false
	 */
	const OPTION_THROW_5xx = "throw5xx";

	/**
	 * Common API content types
	 */
	const CONTENT_TYPE_JSON = "application/json";
	const CONTENT_TYPE_XML = "application/xml";
	const CONTENT_TYPE_FORM = "application/x-www-form-urlencoded";

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
		self::OPTION_RESPONSE_CLASS => "ActiveResource\\Response",
		self::OPTION_COLLECTION_CLASS => "ActiveResource\\Collection",
		self::OPTION_UPDATE_METHOD => "put",
		self::OPTION_UPDATE_DIFF => false,
		self::OPTION_MIDDLEWARE => [],
		self::OPTION_LOG => false,
		self::OPTION_HTTP_VERSION => "1.1",
		self::OPTION_THROW_4xx => true,
		self::OPTION_THROW_5xx => true
	];

	/**
	 * @param ClientInterface $httpClient
	 * @param RequestFactoryInterface $requestFactory
	 * @param StreamFactoryInterface $streamFactory
	 * @param ResponseFactoryInterface $responseFactory
	 * @param array<string,mixed> $options
	 */
	public function __construct(
		ClientInterface $httpClient,
		RequestFactoryInterface $requestFactory,
		StreamFactoryInterface $streamFactory,
		ResponseFactoryInterface $responseFactory,
		array $options = [])
	{
		$this->httpClient = $httpClient;
		$this->requestFactory = $requestFactory;
		$this->streamFactory = $streamFactory;
		$this->responseFactory = $responseFactory;

		if( $options ){
			foreach( $options as $option => $value ){
				$this->setOption($option, $value);
			}
		}
	}

	/**
	 * Create a Connection instance with default settings.
	 *
	 * @param array<string,mixed> $options
	 * @return Connection
	 */
	public static function create(array $options = []): Connection
	{
		return new self(
			new Shuttle,
			new RequestFactory,
			new StreamFactory,
			new ResponseFactory,
			$options
		);
	}

	/**
	 * Set an option on the connection.
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
	 * @param string|null $username
	 * @param string|null $password
	 * @return Connection
	 */
	public function useBasicAuthorization(?string $username, ?string $password): Connection
	{
		$this->options[self::OPTION_DEFAULT_HEADERS] = \array_merge(
			$this->options[self::OPTION_DEFAULT_HEADERS],
			["Authorization" => "Basic " . \base64_encode("{$username}:{$password}")]
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
			["Authorization" => "Bearer {$token}"]
		);

		return $this;
	}

	/**
	 * Builds a PSR-7 Request instance.
	 *
	 * @param string $method HTTP method
	 * @param string $uri Absolute or relative URI.
	 * @param array<string,string> $queryParams Query parameters.
	 * @param string|null $body Serialized body of the request.
	 * @param array<string,string> $headers Headers to include in request.
	 * @return RequestInterface
	 */
	public function buildRequest(string $method, string $uri, array $queryParams = [], string $body = null, array $headers = []): RequestInterface
	{
		$uri = \sprintf(
			"%s/%s",
			\trim($this->getOption(self::OPTION_BASE_URI) ?? "", "/"),
			\trim($uri, "/")
		);

		if( $queryParams ){
			$uri = \sprintf(
				"%s?%s",
				$uri,
				\http_build_query($queryParams)
			);
		}

		$request = $this->requestFactory->createRequest(
			\strtoupper($method),
			\trim($uri, "/")
		);

		$headers = \array_merge(
			$this->getOption(self::OPTION_DEFAULT_HEADERS) ?? [],
			$headers
		);

		foreach( $headers as $header => $value ){
			$request = $request->withHeader($header, $value);
		}

		$request = $request->withProtocolVersion($this->getOption(self::OPTION_HTTP_VERSION ?? "1.1"));

		if( $body ){
			$request = $request->withBody(
				$this->streamFactory->createStream($body)
			);
		}

		if( \in_array($request->getMethod(), ["POST","PUT","PATCH"]) &&
			$request->hasHeader("Content-Type") === false &&
			$this->hasOption(self::OPTION_DEFAULT_CONTENT_TYPE) ){
			$request = $request->withHeader(
				"Content-Type",
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
		// Save the request object so it may be retrieved
		$this->request = $request;

		// Capture start time (for logging requests)
		$start = \microtime(true);

		// Run the request
		$response = $this->run(
			$request,
			function(RequestInterface $request): ResponseInterface {
				try {

					$response = $this->httpClient->sendRequest($request);
				}
				catch( ClientExceptionInterface $clientException ){
					throw new RequestException(
						$request,
						null,
						$clientException->getMessage(),
						$clientException->getCode(),
						$clientException
					);
				}

				return $response;
			}
		);

		// Capture end time
		$stop = \microtime(true);

		// Save the response object so it may be retrieved
		$this->response = $response;

		// Should we log this request?
		if( $this->getOption(self::OPTION_LOG) ){
			$this->addLog($request, $response, (int) \round(($stop-$start) * 1000));
		}

		if( $this->shouldThrow($response) ){
			throw new RequestException(
				$request,
				$response,
				$response->getReasonPhrase(),
				$response->getStatusCode()
			);
		}

		return $response;
	}

	/**
	 * Return whether the response status is in the 200 range.
	 *
	 * @param ResponseInterface $response
	 * @return boolean
	 */
	public function isResponseSuccessful(ResponseInterface $response): bool
	{
		return $response->getStatusCode()>=200 && $response->getStatusCode()<300;
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
		$middlewareStack = \array_map(
			function($layer): MiddlewareInterface {
				if( \is_string($layer) ){
					$layer = new $layer;
				}

				if( $layer instanceof MiddlewareInterface === false ){
					throw new UnexpectedValueException("Middleware layer is not an instance of MiddlewareInterface");
				}

				return $layer;
			},
			$middleware
		);

		return \array_reduce(
			$middlewareStack,
			function(callable $next, object $middleware): Closure {
				return function(RequestInterface $request) use ($next, $middleware): ResponseInterface {
					return $middleware->handle($request, $next);
				};
			},
			function(RequestInterface $request) use ($kernel): ResponseInterface {
				return $kernel($request);
			}
		);
	}

	/**
	 * Should an exception be thrown for the given Response.
	 *
	 * @param ResponseInterface $response
	 * @return boolean
	 */
	private function shouldThrow(ResponseInterface $response): bool
	{
		return ($response->getStatusCode() >= 400 && $response->getStatusCode() < 500 &&
			$this->getOption(self::OPTION_THROW_4xx)) ||

			($response->getStatusCode() >= 500 &&
			$this->getOption(self::OPTION_THROW_5xx));
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