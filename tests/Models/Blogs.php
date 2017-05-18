<?php

namespace Tests\Models;


use ActiveResource\Model;

class Blogs extends Model
{
	public function author($data)
	{
		return $this->includesOne(Users::class, $data);
	}

	public function comments($data)
	{
		return $this->includesMany(Comments::class, $data);
	}
}