<?php

declare(strict_types=1);

namespace Support;

use JsonException;
use InvalidArgumentException;

final class Arr
{
    public const int
        USE_VALUE = 0,
        USE_BOTH  = 1,
        USE_KEY   = 2;

    /**
     * @param array<array-key, mixed> $array
     * @param mixed                   $match
     * @param int<0,2>                $mode
     *
     * @return null|int|string
     */
    public static function search(
        array $array,
        mixed $match,
        int   $mode = Arr::USE_VALUE,
    ) : string|int|null {
        trigger_deprecation(
            'Support\Arr',
            '_dev',
            __METHOD__.' deprecated',
        );

        foreach ( $array as $key => $value ) {
            if ( \is_callable( $match ) && match ( $mode ) {
                Arr::USE_VALUE => $match( $value ),
                Arr::USE_KEY   => $match( $key ),
                Arr::USE_BOTH  => $match( $value, $key ),
            } ) {
                return $key;
            }

            if ( $value === $match ) {
                return $key;
            }

            if ( \is_array( $value ) && self::search( $value, $match, $mode ) ) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Return the closest key or value that `$match` in the provided `$array`.
     *
     * @wip
     * @link https://stackoverflow.com/questions/5464919/find-a-matching-or-closest-value-in-an-array
     *
     * @param int|string              $match
     * @param array<array-key, mixed> $array
     *
     * @return null|string
     */
    public static function closest( int|string $match, array $array ) : ?string
    {
        trigger_deprecation(
            'Support\Arr',
            '_dev',
            __METHOD__.' deprecated',
        );
        // TODO : Match key/value toggle
        // TODO : closest int/float round up/down
        // TODO : closest string match - str_starts_with / other algo?
        // TODO : option to return key/value of match
        // TODO : return FALSE on no match

        /** @var ?string $closest */
        $closest = null;

        foreach ( $array as $item ) {
            if ( ! \is_numeric( $item ) ) {
                throw new InvalidArgumentException( 'Array item must be numeric.' );
            }
            if ( $closest === null
                 || \abs( (int) $match - (int) $closest )
                    > \abs( (int) $item - (int) $match )
            ) {
                $closest = $item;
            }
        }
        return $closest;
    }

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
        array     $array,
        ?callable $callback = null,
        int       $mode = Arr::USE_VALUE,
    ) : array {
        trigger_deprecation(
            'Support\Arr',
            '_dev',
            __METHOD__.' deprecated',
        );
        $callback ??= static fn( $v ) => ! is_empty( $v );
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
        array     $array,
        ?callable $callback = null,
        int       $mode = Arr::USE_VALUE,
    ) : array {
        trigger_deprecation(
            'Support\Arr',
            '_dev',
            __METHOD__.' deprecated',
        );

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
     * @param bool                    $preserveKeys
     * @param bool                    $filter
     * @param self::USE*              $filterMode
     *
     * @return array<array-key, mixed>
     */
    public static function flatten(
        array         $array,
        bool          $preserveKeys = false,
        bool|callable $filter = false,
        int           $filterMode = Arr::USE_VALUE,
    ) : array {
        trigger_deprecation(
            'Support\Arr',
            '_dev',
            __METHOD__.' deprecated',
        );
        $result = [];

        \array_walk_recursive(
            $array,
            match ( $preserveKeys ) {
                true => function( $v, $k ) use ( &$result ) : void {
                    $result[$k] = $v;
                },
                false => function( $v ) use ( &$result ) : void {
                    $result[] = $v;
                },
            },
        );

        if ( $filter === false ) {
            return $result;
        }

        $callback = $filter === true ? static fn( $v ) => ! is_empty( $v ) : $filter;

        return \array_filter( $result, $callback, $filterMode );
    }

    /**
     * @param array<array-key, scalar> $array
     * @param bool                     $caseSensitive
     *
     * @return array<array-key, scalar>
     */
    public static function uniqueScalar(
        array $array,
        bool  $caseSensitive = false,
    ) : array {
        trigger_deprecation(
            'Support\Arr',
            '_dev',
            __METHOD__.' deprecated',
        );
        if ( ! $caseSensitive ) {
            $array = \array_map(
                static fn( $value ) => \is_string( $value ) ? \strtolower( $value ) : $value,
                $array,
            );
        }

        return \array_unique( $array, SORT_REGULAR );
    }

    /**
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     */
    public static function uniqueValues(
        array $array,
    ) : array {
        trigger_deprecation(
            'Support\Arr',
            '_dev',
            __METHOD__.' deprecated',
        );
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
    public static function hasKeys(
        array         $array,
        int|string ...$keys,
    ) : bool {
        trigger_deprecation(
            'Support\Arr',
            '_dev',
            __METHOD__.' deprecated',
        );

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
    public static function replaceKey(
        array      $array,
        int|string $key,
        int|string $replacement,
    ) : array {
        trigger_deprecation(
            'Support\Arr',
            '_dev',
            __METHOD__.' deprecated',
        );
        $keys  = \array_keys( $array );
        $index = \array_search( $key, $keys, true );

        if ( $index !== false ) {
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
    public static function asObject(
        array|object $array,
        bool         $filter = false,
    ) : object {
        trigger_deprecation(
            'Support\Arr',
            '_dev',
            __METHOD__.' deprecated',
        );
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
        catch ( JsonException ) {
            return (object) $array;
        }
    }
}
