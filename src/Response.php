<?php
/**
 * Created by PhpStorm.
 * User: brent
 * Date: 1/12/17
 * Time: 12:12 PM
 */

namespace ActiveResource;


class Response extends ResponseAbstract
{
    /**
     * Parse/decode the response body
     *
     * @param string $body
     * @return mixed
     */
    public function parse($body)
    {
        return json_decode($body);
    }

    /**
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->getStatusCode() < 400;
    }
}