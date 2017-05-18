<?php
/**
 * Created by PhpStorm.
 * User: brent
 * Date: 5/18/17
 * Time: 1:18 PM
 */

namespace Tests\Models;


use ActiveResource\Model;

class Comments extends Model
{
	public function author($data)
	{
		return $this->includesOne(Users::class, $data);
	}
}