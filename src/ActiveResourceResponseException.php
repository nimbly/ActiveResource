<?php

namespace ActiveResource;

use Psr\Http\Message\ResponseInterface;

class ActiveResourceResponseException extends \RuntimeException
{
	/**
	 * Response instance.
	 *
	 * @var ResponseInterface
	 */
	protected $response;

	public function __construct(ResponseInterface $response)
	{
		parent::__construct($response->getReasonPhrase(), $response->getStatusCode(), null);

		$this->response = $response;
	}

	public function getResponse(): ResponseInterface
	{
		return $this->response;
	}
}