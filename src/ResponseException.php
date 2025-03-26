<?php

namespace Nimbly\ActiveResource;

use Exception;
use Throwable;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ResponseException extends Exception
{
	public function __construct(
		protected RequestInterface $request,
		protected ?ResponseInterface $response = null,
		?Throwable $previous = null)
	{
		parent::__construct(
			$response?->getReasonPhrase() ?? "An error occured.",
			$response?->getStatusCode() ?? 0,
			$previous
		);
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
	 * Get the ResponseInterface instance.
	 *
	 * @return ResponseInterface|null
	 */
	public function getResponse(): ?ResponseInterface
	{
		return $this->response;
	}
}