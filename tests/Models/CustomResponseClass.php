<?php
/**
 * Created by PhpStorm.
 * User: brent
 * Date: 12/3/17
 * Time: 9:27 AM
 */

namespace Tests\Models;


use ActiveResource\ResponseAbstract;

class CustomResponseClass extends ResponseAbstract
{
    public function decode($body)
    {
        return json_decode($body);
    }

    public function isSuccessful()
    {
        return ($this->getStatusCode() >= 200 && $this->getStatusCode() < 400);
    }
}