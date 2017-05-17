<?php

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
     * Array of response headers
     *
     * @var array
     */
    protected $headers = [];

    /**
     * Array of HTTP response codes that ActiveResource should throw an exception when encountering
     *
     * @var array
     */
    protected $throwable = [];

    /**
     * Raw response body
     *
     * @var
     */
    protected $body;

    /**
     * The fully parsed/decoded payload
     * @var mixed
     */
    protected $payload;


    /**
     * @var array
     */
    protected $statusTexts = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',            // RFC2518
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',          // RFC4918
        208 => 'Already Reported',      // RFC5842
        226 => 'IM Used',               // RFC3229
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',    // RFC7238
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',                                               // RFC2324
        421 => 'Misdirected Request',                                         // RFC7540
        422 => 'Unprocessable Entity',                                        // RFC4918
        423 => 'Locked',                                                      // RFC4918
        424 => 'Failed Dependency',                                           // RFC4918
        425 => 'Reserved for WebDAV advanced collections expired proposal',   // RFC2817
        426 => 'Upgrade Required',                                            // RFC2817
        428 => 'Precondition Required',                                       // RFC6585
        429 => 'Too Many Requests',                                           // RFC6585
        431 => 'Request Header Fields Too Large',                             // RFC6585
        451 => 'Unavailable For Legal Reasons',                               // RFC7725
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates (Experimental)',                      // RFC2295
        507 => 'Insufficient Storage',                                        // RFC4918
        508 => 'Loop Detected',                                               // RFC5842
        510 => 'Not Extended',                                                // RFC2774
        511 => 'Network Authentication Required',                             // RFC6585
    ];

    /**
     * Response constructor.
     * @param ResponseInterface $response
     */
    public function __construct(ResponseInterface $response = null)
    {
        if( $response ){
            $this->setStatusCode($response->getStatusCode());
            $this->setHeaders($response->getHeaders());
            $this->setBody($response->getBody()->getContents());
        }
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
     * @param $statusCode
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;
    }

    /**
     * Get the response status code
     *
     * @return string
     */
    public function getStatusPhrase()
    {
        if( array_key_exists($this->getStatusCode(), $this->statusTexts) ){
            return $this->statusTexts[$this->getStatusCode()];
        }

        return null;
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
                return $value;
            }
        }

        return null;
    }

    /**
     * @param $header
     * @param $value
     */
    public function setHeader($header, $value)
    {
        $this->headers[$header] = $value;
    }


    /**
     * @param array $headers
     */
    public function setHeaders(array $headers)
    {
        foreach( $headers as $header => $value ){
            if( is_array($value) && count($value) == 1 ) {
                $this->setHeader($header, $value[0]);
            }
            else {
                $this->setHeader($header, $value);
            }
        }
    }

    /**
     * Get the raw (pre-decoded) response body
     *
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Set the raw response body and trigger the decoder to update the payload
     *
     * @param $body
     */
    public function setBody($body)
    {
        $this->body = $body;
        $this->payload = $this->decode($this->body);
    }

    /**
     * Get the parsed/decoded response payload
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
     * Decode the response body for processing
     *
     * @param string $body
     * @return mixed
     */
    abstract public function decode($body);

    /**
     * Whether this request was successful or not
     *
     * @return bool
     */
    abstract public function isSuccessful();
}