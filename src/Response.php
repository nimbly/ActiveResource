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
     * Parse/decode the response payload
     *
     * @param string $payload
     * @return mixed
     */
    public function parse($payload)
    {
        return json_decode($payload);
    }

    /**
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->getStatusCode() < 400;
    }
}