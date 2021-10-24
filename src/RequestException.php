<?php

namespace ActiveResource;

use Exception;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class RequestException extends Exception
{
	/**
	 * RequestInterface
	 *
	 * @var RequestInterface
	 */
	protected RequestInterface $request;

    /**
     * ResponseInterface instance.
     *
     * @var ResponseInterface|null
     */
    protected ?ResponseInterface $response;

    /**
	 * @param RequestInterface $request
     * @param ResponseInterface|null $response
     */
    public function __construct(
		RequestInterface $request,
		?ResponseInterface $response,
		string $message,
		int $code,
		?Throwable $previous = null)
    {
		$this->request = $request;
    	$this->response = $response;

        parent::__construct($message, $code, $previous);
    }

	/**
	 * Get the RequestInterface instance.
	 *
	 * @return RequestInterface
	 */
	public function getRequest(): RequestInterface
	{
		return $this->request;
	}

	/**
	 * Is there an ResponseInterface instance available.
	 *
	 * @return boolean
	 */
	public function hasResponse(): bool
	{
		return $this->response !== null;
	}

    /**
     * Get the Response instance.
     *
     * @return ResponseInterface|null
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}