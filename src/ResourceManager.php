<?php

namespace Nimbly\ActiveResource;

use ReflectionClass;
use Nimbly\ActiveResource\Attributes\ResourceName;
use Nimbly\ActiveResource\Attributes\ConnectionName;
use Nimbly\ActiveResource\Attributes\CollectionProperty;
use Nimbly\ActiveResource\Attributes\ResourceIdentifier;

class ResourceManager
{
	private static array $connections = [];
	private static array $names = [];
	private static array $identifiers = [];
	private static array $collections = [];

	/**
	 * Get the connection instance for the given resource.
	 *
	 * @param class-string $resource
	 * @throws ResourceException
	 * @return string
	 */
	public static function getConnectionName(string $resource): string
	{
		if( !isset(self::$connections[$resource]) ){

			$attribute = self::getResourceAttributes($resource, ConnectionName::class);

			if( $attribute ){
				$connectionAttribute = $attribute[0]->newInstance();
				$name = $connectionAttribute->getName();
			}

			self::$connections[$resource] = $name ?? "default";
		}

		return self::$connections[$resource];
	}

	public static function getCollectionProperty(Resource|string $resource): ?string
	{
		if( $resource instanceof Resource ){
			$resource = $resource::class;
		}

		if( \array_key_exists($resource, self::$collections) === false ){
			$attribute = self::getResourceAttributes($resource, CollectionProperty::class);

			if( $attribute ){
				$collectionPropertyAttribute = $attribute[0]->newInstance();
				$collectionProperty = $collectionPropertyAttribute->getCollectionProperty();
			}

			self::$collections[$resource] = $collectionProperty ?? null;
		}

		return self::$collections[$resource];
	}

	/**
	 * Get the Resource's resource name (defaults to lowercase class name).
	 *
	 * @param Resource|class-string $resource
	 * @return string
	 */
	public static function getResourceName(Resource|string $resource): string
	{
		if( $resource instanceof Resource ){
			$resource = $resource::class;
		}

		if( !isset(self::$names[$resource]) ){
			$attribute = self::getResourceAttributes($resource, ResourceName::class);

			if( $attribute ){
				$connectionAttribute = $attribute[0]->newInstance();
				$resourceName = $connectionAttribute->getName();
			}
			else {
				$pos = \strrpos($resource, "\\");

				if( $pos !== false ) {
					$resourceName = \substr($resource, $pos + 1);
				}
				else {
					$resourceName = $resource;
				}
			}

			self::$names[$resource] = \strtolower($resourceName);
		}

		return self::$names[$resource];
	}

	/**
	 * Get the resource identifier. Defaults to "id".
	 *
	 * @param Resource|class-string $resource
	 * @return string
	 */
	public static function getResourceIdentifier(Resource|string $resource): string
	{
		if( $resource instanceof Resource ){
			$resource = $resource::class;
		}

		if( !isset(self::$identifiers[$resource]) ){
			$attribute = self::getResourceAttributes($resource, ResourceIdentifier::class);

			if( $attribute ){
				$connectionAttribute = $attribute[0]->newInstance();
				$resourceIdentifier = $connectionAttribute->getIdentifier();
			}

			self::$identifiers[$resource] = $resourceIdentifier ?? "id";
		}

		return self::$identifiers[$resource];
	}

	/**
	 * Get attributes for Resource.
	 *
	 * @param Resource|class-string $resource
	 * @param string|null $attribute
	 * @return array<\ReflectionAttribute>
	 */
	protected static function getResourceAttributes(
		Resource|string $resource,
		?string $attribute = null): array
	{
		$reflectionClass = new ReflectionClass($resource);
		/**
		 * @psalm-suppress InvalidArgument
		 */
		return $reflectionClass->getAttributes($attribute);
	}
}