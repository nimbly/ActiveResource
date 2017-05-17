<?php

namespace ActiveResource;


class Response extends ResponseAbstract
{
    /**
     * Decode the response body
     *
     * @param string $body
     * @return mixed
     */
    public function decode($body)
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