<?php

declare(strict_types=1);

namespace Support;

use ReflectionClass;
use BadMethodCallException;
use ReflectionException;
use ReflectionMethod;
use Reflector;

/**
 * Helper methods for the native {@see Reflector} API.
 */
final class Reflect
{
    /**
     * Assertive construction of a ReflectionClass.
     *
     * - Ensures the provided `class_exists`.
     * - Wraps {@see ReflectionException} in a {@see BadMethodCallException}.
     *
     * @template T of object
     *
     * @param class-string<T>|T $class
     *
     * @return ReflectionClass<T>
     */
    public static function class( object|string $class ) : ReflectionClass
    {
        \assert( \class_exists( \is_object( $class ) ? $class::class : $class ) );

        try {
            return new ReflectionClass( $class );
        }
        catch ( ReflectionException $exception ) {
            throw new BadMethodCallException( $exception->getMessage(), 500, $exception );
        }
    }

    /**
     * Assertive construction of a ReflectionClass.
     *
     *  - Ensures the provided `class_exists`.
     *  - Wraps {@see ReflectionException} in a {@see BadMethodCallException}.
     *
     * @param class-string|object $class
     * @param string              $method
     *
     * @return ReflectionMethod
     */
    public static function method( object|string $class, string $method ) : ReflectionMethod
    {
        \assert( \class_exists( \is_object( $class ) ? $class::class : $class ) );

        try {
            if ( $class instanceof ReflectionClass ) {
                return $class->getMethod( $method );
            }
            return new ReflectionMethod( $class, $method );
        }
        catch ( ReflectionException $exception ) {
            throw new BadMethodCallException( $exception->getMessage(), 500, $exception );
        }
    }

    /**
     * Retrieve a single {@see \Attribute} from a provided {@see Reflector instance}.
     *
     * Will throw {@see BadMethodCallException} if:
     * - Passed `$reflector` doesn't provide `getAttributes`.
     * - `$reflector` is missing the requested `$attribute`.
     * - `$reflector` has multiple instances of requested `$attribute`.
     *
     * @template T of object
     *
     * @param object|string   $reflector
     * @param class-string<T> $attribute
     *
     * @return null|T
     */
    public static function getAttribute( object|string $reflector, string $attribute ) : ?object
    {
        \assert( \is_object( $reflector ) );

        if ( ! $reflector instanceof Reflector ) {
            $reflector = self::class( $reflector );
        }

        \assert( $reflector instanceof Reflector );

        if ( ! \method_exists( $reflector, 'getAttributes' ) ) {
            throw new BadMethodCallException( "The passed reflector does not offer the 'getAttributes' method." );
        }

        $attributes = $reflector->getAttributes( $attribute );

        if ( empty( $attributes ) ) {
            return null;
        }

        if ( \count( $attributes ) !== 1 ) {
            throw new BadMethodCallException( "The passed reflector does not offer the 'getAttributes' method." );
        }

        return $attributes[0]->newInstance();
    }
}
