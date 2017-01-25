<?php
/**
 * Created by PhpStorm.
 * User: brent
 * Date: 1/11/17
 * Time: 10:53 PM
 */

namespace ActiveResource;


use Psr\Http\Message\ResponseInterface;

abstract class ResponseAbstract
{
    /**
     * HTTP response code
     *
     * @var int
     */
    protected $statusCode;

    /**
     * Status description
     *
     * @var string
     */
    protected $statusPhrase;

    /**
     * Array of response headers
     *
     * @var \string[][]
     */
    protected $headers;

    /**
     * Array of HTTP response codes that ActiveResource should throw an exception when encountering
     *
     * @var array
     */
    protected $throwable = [500];

    /**
     * The fully decoded payload
     * @var mixed
     */
    protected $payload;

    /**
     * Response constructor.
     * @param ResponseInterface $response
     */
    public function __construct(ResponseInterface $response)
    {
        $this->statusCode = $response->getStatusCode();
        $this->statusPhrase = $response->getReasonPhrase();
        $this->headers = $response->getHeaders();
        $this->payload = $this->parse($response->getBody()->getContents());
    }

    /**
     * Get the response status code
     *
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Get the response status code
     *
     * @return string
     */
    public function getStatusPhrase()
    {
        return $this->statusPhrase;
    }

    /**
     * Get all headers
     *
     * @return mixed
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Get a specific header
     *
     * @param $name
     * @return string|null
     */
    public function getHeader($name)
    {
        foreach( $this->headers as $header => $value)
        {
            if( strtolower($header) == strtolower($name) ){
                if( is_array($value) &&
                    count($value) == 1 ){
                    return $value[0];
                }

                return $value;
            }
        }

        return null;
    }

    /**
     * Get the decoded response payload
     *
     * @return mixed
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * Is this response a throwable error
     *
     * @return bool
     */
    public function isThrowable()
    {
        return in_array($this->getStatusCode(), $this->throwable);
    }

    /**
     * Parse the response for processing
     *
     * @param string $payload
     * @return mixed
     */
    abstract public function parse($payload);

    /**
     * Whether this request was successful or not
     *
     * @return bool
     */
    abstract public function isSuccessful();
}