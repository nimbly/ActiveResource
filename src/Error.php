<?php
/**
 * Created by PhpStorm.
 * User: brent
 * Date: 1/25/17
 * Time: 11:17 AM
 */

namespace ActiveResource;


class Error extends ErrorAbstract
{
    public function getMessage()
    {
        return $this->response->getStatusPhrase();
    }
}