<?php

namespace ActiveResource;


class ActiveResourceResponseException extends \RuntimeException
{
	/**
	 * Response instance.
	 *
	 * @var ResponseAbstract
	 */
	protected $response = null;

	public function __construct(ResponseAbstract $response)
	{
		parent::__construct($response->getStatusPhrase(), $response->getStatusCode(), null);

		$this->response = $response;
	}

	public function getResponse(): ResponseAbstract
	{
		return $this->response;
	}
}