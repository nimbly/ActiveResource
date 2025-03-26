<?php

namespace Nimbly\ActiveResource;

use RuntimeException;
use Nimbly\Shuttle\Shuttle;
use Nimbly\Announce\Dispatcher;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Nimbly\Capsule\Factory\StreamFactory;
use Nimbly\Capsule\Factory\RequestFactory;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Nimbly\ActiveResource\Events\ResourceEventAbstract;

class ConnectionManager
{
	/**
	 * ConnectionManager singleton instance.
	 *
	 * @var self|null
	 */
	protected static ?ConnectionManager $instance;

	/**
	 * @param array<string,Connection> $connections
	 * @param ClientInterface $client
	 * @param RequestFactoryInterface $requestFactory
	 * @param StreamFactoryInterface $streamFactory
	 * @param EventDispatcherInterface|null $eventDispatcher
	 */
	protected function __construct(
		protected array $connections = [],
		protected ClientInterface $httpClient = new Shuttle,
		protected RequestFactoryInterface $requestFactory = new RequestFactory,
		protected StreamFactoryInterface $streamFactory = new StreamFactory,
		protected ?EventDispatcherInterface $eventDispatcher = null,
	)
	{
	}

	/**
	 * ActiveResource ConnectionManager init factory.
	 *
	 * @param array<string,Connection> $connections
	 * @param ClientInterface $httpClient
	 * @param RequestFactoryInterface $requestFactory
	 * @param StreamFactoryInterface $streamFactory
	 * @param EventDispatcherInterface $eventDispatcher
	 * @return ConnectionManager
	 */
	public static function init(
		array $connections = [],
		ClientInterface $httpClient = new Shuttle,
		RequestFactoryInterface $requestFactory = new RequestFactory,
		StreamFactoryInterface $streamFactory = new StreamFactory,
		?EventDispatcherInterface $eventDispatcher = null,
	): ConnectionManager
	{
		self::$instance =  new self(
			connections: $connections,
			httpClient: $httpClient,
			requestFactory: $requestFactory,
			streamFactory: $streamFactory,
			eventDispatcher: $eventDispatcher,
		);

		return self::$instance;
	}

	/**
	 * Add a new connection instance to the pool.
	 *
	 * @param Connection $connection
	 * @param string $name Connection name, defaults to `default`.
	 * @return void
	 */
	public function addConnection(Connection $connection, string $name = "default"): void
	{
		$this->connections[$name] = $connection;
	}

	/**
	 * Get the Manager instance.
	 *
	 * @return ConnectionManager
	 */
	public static function getInstance(): ConnectionManager
	{
		if( empty(self::$instance) ){
			throw new RuntimeException(
				"ConnectionManager instance has not been initialized. Did you run ConnectionManager::init()?."
			);
		}

		return self::$instance;
	}

	/**
	 * Get the ClientInterfaceInstance.
	 *
	 * @return ClientInterface
	 */
	public function getHttpClient(): ClientInterface
	{
		return $this->httpClient;
	}

	/**
	 * Get the RequestFactoryInterface instance.
	 *
	 * @return RequestFactoryInterface
	 */
	public function getRequestFactory(): RequestFactoryInterface
	{
		return $this->requestFactory;
	}

	/**
	 * Get the StreamFactoryInterface instance.
	 *
	 * @return StreamFactoryInterface
	 */
	public function getStreamFactory(): StreamFactoryInterface
	{
		return $this->streamFactory;
	}

	/**
	 * Get a Connection from the manager.
	 *
	 * @param string|null $name The name of the connection to get. If null is passed, the default connection is returned.
	 * @return Connection|null
	 */
	public function getConnection(?string $name = null): ?Connection
	{
		return $this->connections[$name ?? "default"] ?? null;
	}

	/**
	 * Dispatch an event.
	 *
	 * @param ResourceEventAbstract $event
	 * @return void
	 */
	public function dispatch(ResourceEventAbstract $event): void
	{
		$this->eventDispatcher?->dispatch($event);
	}

	/**
	 * Build a RequestInterface instance for this resource.
	 *
	 * @param string $method
	 * @param string $uri
	 * @param string|null $body
	 * @param array<string,mixed> $query
	 * @param array<string,mixed> $headers
	 * @return RequestInterface
	 */
	protected function buildRequest(
		string $method,
		string $uri,
		?string $body = null,
		array $query = [],
		array $headers = [],
		string $version = "1.1"): RequestInterface
	{
		if( $query ){
			$uri .= ("?" . \http_build_query($query));
		}

		// Create the RequestInterface instance.
		$request = $this->getRequestFactory()->createRequest(
			\strtoupper($method),
			$uri
		);

		// Add body to Request
		if( $body ){
			$request = $request->withBody(
				$this->getStreamFactory()->createStream($body)
			);
		}

		// Attach the headers to Request
		foreach( $headers as $header => $value ){
			$request = $request->withAddedHeader($header, $value);
		}

		// Set the protocol version
		$request = $request->withProtocolVersion($version);

		return $request;
	}

	/**
	 * @param string|Connection $connection Connection name or Connection instance to send the request on.
	 * @param string $method The HTTP method to use.
	 * @param string $uri The URI of the request.
	 * @param mixed $body The body of the request. Can be `null` for an empty body.
	 * @param array<string,string> $query Query parameters to send with request.
	 * @param array<string,string> $headers HTTP headers to send with request.
	 * @throws ResponseException
	 * @return Response Returns the deserialized response body and headers.
	 */
	public function sendRequest(
		string|Connection $connection,
		string $method,
		string $uri,
		mixed $body = null,
		array $query = [],
		array $headers = []): Response
	{
		if( \is_string($connection) ){
			$cxn = $this->getConnection($connection);

			if( empty($cxn) ){
				throw new ConnectionException(
					\sprintf("Connection \"%s\" not found.", $connection)
				);
			}

			$connection = $cxn;
		}

		$request = $this->buildRequest(
			$method,
			\trim($connection->getHost(), "/") . "/" . $uri,
			$connection->serialize($body),
			\array_merge($query, $connection->getQuery()),
			\array_merge($headers, $connection->getHeaders()),
			$connection->getOption(Connection::OPTION_HTTP_VERSION) ?? "1.1"
		);

		try {

			$response = $this->getHttpClient()->sendRequest($request);
		}
		catch( ClientExceptionInterface $exception ){
			throw new ResponseException(
				request: $request,
				previous: $exception
			);
		}

		if( $response->getStatusCode() >= 400 ){
			throw new ResponseException(
				request: $request,
				response: $response,
			);
		}

		return new Response(
			payload: $connection->deserialize($response->getBody()->getContents()),
			headers: $response->getHeaders()
		);
	}
}