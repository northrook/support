<?php

declare(strict_types=1);

namespace Support;

use ReflectionClass;
use BadMethodCallException;
use ReflectionException;

final class Reflect
{
    /**
     * Constructs a ReflectionClass.
     *
     * @link https://php.net/manual/en/reflectionclass.construct.php PHP Docs
     *
     * @template T of object
     *
     * @param class-string<T>|T $objectOrClass
     *
     * @return ReflectionClass<T>
     */
    public static function class( object|string $objectOrClass ) : object
    {
        try {
            return new ReflectionClass( $objectOrClass );
        }
        catch ( ReflectionException $exception ) {
            throw new BadMethodCallException( $exception->getMessage(), 500, $exception );
        }
    }
}
