<?php

namespace Nimbly\ActiveResource\Tests\Resources;

use DateTime;
use Nimbly\ActiveResource\Attributes\ConnectionName;
use Nimbly\ActiveResource\Attributes\ResourceIdentifier;
use Nimbly\ActiveResource\Resource;
use Nimbly\ActiveResource\Attributes\ResourceName;

/**
 * @property string	$id
 * @property string $isbn
 * @property string $title
 * @property string $author
 * @property string $publisher
 * @property string $genre
 * @property string $status
 * @property DateTime $published_at
 * @property DateTime $created_at
 */
#[ResourceName("books")]
#[ResourceIdentifier("id")]
#[ConnectionName("default")]
class Book extends Resource
{
	protected array $fillableProperties = ["title", "author", "publisher", "genre", "status", "published_at", "created_at"];
	protected array $excludedProperties = ["genre"];

	/**
	 * Get the DateTime instance for CreatedAt.
	 *
	 * @param string|null $date
	 * @return DateTime
	 */
	protected function getCreatedAtProperty(?string $date): DateTime
	{
		return new DateTime($date ?? "now");
	}

	/**
	 * Force status to be lower case.
	 *
	 * @param string $status
	 * @return string
	 */
	protected function setStatusProperty(string $status): string
	{
		return \strtolower($status);
	}
}