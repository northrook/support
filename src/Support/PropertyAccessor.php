<?php

declare(strict_types=1);

namespace Support;

use LogicException;

/**
 * Access properties of an object.
 *
 *  - Does not allow setting properties.
 *  - Allow checking if a property exists.
 *
 * Recommended usage:
 * - Use `match()` to access properties in the `__get()` method.
 * - Only match desired properties, avoiding `$this->{property}` where possible.
 * - Default should return `null` or `false`.
 * ```
 * // example
 * public function __get( string $property ) : null|string|int {
 *     return match( $property ) {
 *         'name' => $this->name,
 *         'age' => $this->age,
 *         default => null,
 *     };
 * }
 * ```
 *
 * @author Martin Nielsen <mn@northrook.com>
 */
trait PropertyAccessor
{
    abstract public function __get( string $property );

    /**
     * Check if the property exists.
     *
     * @param string $property
     *
     * @return bool
     */
    public function __isset( string $property ) : bool
    {
        return isset( $this->{$property} );
    }

    /**
     * The {@see PropertyAccessor} trait does not allow setting properties.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @throws LogicException
     */
    public function __set( string $name, mixed $value )
    {
        throw new LogicException( $this::class.' properties are read-only.' );
    }
}
