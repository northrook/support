<?php

declare(strict_types=1);

namespace Support;

use function Assert\{isEmpty, isIterable};

final class Arr
{
    public const int
        FILTER_VALUE = 0,
        FILTER_BOTH  = 1,
        FILTER_KEY   = 2;

    /**
     * @template TKey of array-key
     * @template TValue of mixed
     * Default:
     * - Removes `null` and `empty` type values, retains `0` and `false`.
     *
     * @param array<TKey, TValue> $array
     * @param ?callable           $callback
     * @param int-mask<1,2,3>     $mode
     *
     * @return array<TKey, TValue>
     */
    public static function filter(
        array $array,
        ?callable $callback = null,
        int $mode = Arr::FILTER_VALUE,
    ) : array {
        $callback ??= static fn( $v ) => ! isEmpty( $v );
        return \array_filter( $array, $callback, $mode );
    }

    /**
     * Default:
     * - Removes `null` and `empty` type values, retains `0` and `false`.
     *
     * @param array<array-key, mixed> $array
     * @param ?callable               $callback
     * @param int-mask<1,2,3>         $mode
     *
     * @return array<array-key, mixed>
     */
    public static function filterRecursive(
        array $array,
        ?callable $callback = null,
        int $mode = Arr::FILTER_VALUE,
    ) : array {
        foreach ( $array as $key => $value ) {
            if ( \is_array( $value ) ) {
                $array[$key] = ! $value
                        ? Arr::filterRecursive( $value, $callback, $mode )
                        : Arr::filter( $value, $callback, $mode );
            }
            else {
                $array[$key] = $value;
            }
        }

        return Arr::filter( $array );
    }

    /**
     * @param array<array-key, mixed> $array
     * @param bool                    $filter
     *
     * @return array<array-key, mixed>
     */
    public function flatten( array $array, bool $filter = false ) : array
    {
        $result = [];

        foreach ( $array as $key => $value ) {
            if ( isIterable( $value ) ) {
                $value  = \iterator_to_array( $value );
                $result = \array_merge( $result, Arr::flatten( $filter ? Arr::filter( $array ) : $value ) );
            }
            else {
                // Add the value while preserving the key
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array<array-key, scalar> $array
     * @param bool                     $caseSensitive
     *
     * @return array<array-key, scalar>
     */
    public function uniqueScalar( array $array, bool $caseSensitive = false ) : array
    {
        if ( ! $caseSensitive ) {
            $array = \array_map( static fn( $value ) => \is_string( $value ) ? \strtolower( $value ) : $value, $array );
        }

        return \array_unique( $array, SORT_REGULAR );
    }

    /**
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     */
    public function uniqueValues( array $array ) : array
    {
        $unique = [];

        foreach ( $array as $key => $value ) {
            // Check if value is already present
            $isDuplicate = false;

            foreach ( $unique as $existing ) {
                // If it's an object or array, use a deeper comparison
                if ( \is_object( $value ) || \is_array( $value ) ) {
                    if ( $value == $existing ) {
                        $isDuplicate = true;

                        break;
                    }
                }
                elseif ( $value === $existing ) { // For scalar values, use strict comparison
                    $isDuplicate = true;

                    break;
                }
            }

            // Add the value to the result array if it's unique
            if ( ! $isDuplicate ) {
                $unique[$key] = $value;
            }
        }

        return $unique;
    }

    /**
     * Ensures the provided array contains all keys.
     *
     * @param array<array-key, mixed> $array
     * @param array-key               ...$keys
     *
     * @return bool
     */
    public function hasKeys( array $array, int|string ...$keys ) : bool
    {
        foreach ( $keys as $key ) {
            if ( ! \array_key_exists( $key, $array ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array-key               $key
     * @param array-key               $replacement
     *
     * @return array<array-key, mixed>
     */
    public function replaceKey( array $array, int|string $key, int|string $replacement ) : array
    {
        $keys  = \array_keys( $array );
        $index = \array_search( $key, $keys, true );

        if ( false !== $index ) {
            $keys[$index] = $replacement;
            $array        = \array_combine( $keys, $array );
        }

        return $array;
    }

    /**
     * @param array<array-key,mixed>|object $array
     * @param bool                          $filter
     *
     * @return object
     */
    public function asObject( array|object $array, bool $filter = false ) : object
    {
        if ( $filter && \is_array( $array ) ) {
            $array = \array_filter( $array );
        }

        try {
            return (object) \json_decode(
                \json_encode( $array, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT ),
                false,
                512,
                JSON_THROW_ON_ERROR,
            );
        }
        catch ( \JsonException ) {
            return (object) $array;
        }
    }
}
