<?php

declare(strict_types=1);

namespace Support;

use JetBrains\PhpStorm\Deprecated;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionAttribute;
use ReflectionClass;
use BadMethodCallException;
use ReflectionException;
use Reflector;

final class Reflect
{
    /**
     * @template T
     *
     * @param Reflector       $reflector
     * @param class-string<T> $attribute
     * @param bool            $asReflector
     *
     * @return ($asReflector is true ? ReflectionAttribute[] : T)
     */
    public static function getAttribute(
        Reflector $reflector,
        string    $attribute,
        bool      $asReflector = false,
    ) : mixed {
        if ( ! \method_exists( $reflector, 'getAttributes' ) ) {
            throw new BadMethodCallException( "The passed reflector does not offer the 'getAttributes' method." );
        }

        $attributes = $reflector->getAttributes( $attribute );

        if ( \count( $attributes ) !== 1 ) {
            throw new BadMethodCallException( "The passed reflector does not offer the 'getAttributes' method." );
        }

        return $asReflector ? $attributes[0] : $attributes[0]->newInstance();
    }

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
    #[Deprecated]
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
