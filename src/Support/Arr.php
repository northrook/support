<?php

declare(strict_types=1);

namespace Support;

use JsonException;

final class Arr
{
    public const int
        USE_VALUE = 0,
        USE_BOTH  = 1,
        USE_KEY   = 2;

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
            // Check if the value is already present
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
