<?php

declare(strict_types=1);

namespace Support;

use InvalidArgumentException;
use LengthException;
use function Cache\memoize;
use const PHP_MAXPATHLEN;

final class Normalize
{
    /**
     * # Normalise a `string`, assuming returning it as a `key`.
     *
     * - Removes non-alphanumeric characters.
     * - Removes leading and trailing separators.
     * - Converts to lowercase.
     *
     * ```
     * normalizeKey( './assets/scripts/example.js' );
     * // => 'assets-scripts-example-js'
     * ```
     *
     * @param null|array<int, ?string>|string $string
     * @param string                          $separator                = ['-', '_', ''][$any]
     * @param int                             $characterLimit
     * @param bool                            $throwOnIllegalCharacters
     *
     * @return string
     */
    public static function key(
        string|array|null $string,
        string            $separator = '-',
        int               $characterLimit = 0,
        bool              $throwOnIllegalCharacters = false,
    ) : string {
        // Convert to lowercase
        $string = \strtolower( \is_string( $string ) ? $string : \implode( $separator, $string ) );

        // Enforce characters
        if ( $throwOnIllegalCharacters && ! \preg_match( "/^[a-zA-Z0-9_\-{$separator}]+$/", $string ) ) {
            throw new InvalidArgumentException( 'The provided string contains illegal characters. It must only accept ASCII letters, numbers, hyphens, and underscores.');
        }

        // Replace non-alphanumeric characters with the separator
        $string = (string) \preg_replace( "/[^a-z0-9{$separator}]+/i", $separator, $string );

        if ( $characterLimit && \strlen( $string ) >= $characterLimit ) {
            throw new InvalidArgumentException( "The normalized key string exceeds the maximum length of '{$characterLimit}' characters.");
        }

        // Remove leading and trailing separators
        return \trim( $string, $separator );
    }

    /**
     * # Normalise a `string` or `string[]`, assuming it is a `path`.
     *
     * - If an array of strings is passed, they will be joined using the directory separator.
     * - Normalises slashes to system separator.
     * - Removes repeated separators.
     * - Valid paths will be added to the realpath cache.
     * - The resulting string will be cached for this process.
     * - Will throw a {@see LengthException} if the resulting string exceeds {@see PHP_MAXPATHLEN}.
     *
     * ```
     * normalizePath( './assets\\\/scripts///example.js' );
     * // => '.\assets\scripts\example.js'
     * ```
     *
     * @param array<int, ?string>|string $path          the string to normalize
     * @param bool                       $trailingSlash append a trailing slash
     */
    public static function path(
        string|array $path,
        bool         $trailingSlash = false,
    ) : string {
        return memoize(
            function() use ( $path, $trailingSlash ) : string {
                // Remove null and empty parts
                $filtered = \array_filter(
                    Arr::flatten( (array) $path ),
                    static function( $segment ) {
                        \assert(
                            \is_string( $segment ) || \is_null( $segment ),
                            'Each path segment must be a ?string.',
                        );
                        return $segment;
                    },
                );

                // Normalize separators
                $normalize = (array) \str_replace( ['\\', '/'], DIRECTORY_SEPARATOR, $filtered );

                // If the first character of the first segment is a separator, the path is considered relative
                $isRelative = DIRECTORY_SEPARATOR === $normalize[0][0] ?? false;

                $exploded = \explode( DIRECTORY_SEPARATOR, \implode( DIRECTORY_SEPARATOR, $normalize ) );

                // Ensure each part does not start or end with illegal characters
                $varified = \array_map( static fn( $item ) => \trim( $item, " \n\r\t\v\0\\/" ), $exploded );

                // Filter the exploded path, and implode using the directory separator
                $path = \implode( DIRECTORY_SEPARATOR, \array_filter( $varified ) );

                // Preserve intended relative paths
                if ( $isRelative ) {
                    $path = DIRECTORY_SEPARATOR.$path;
                }
                // Add to realpath cache if valid
                else {
                    $path = \realpath( $path ) ?: $path;
                }

                // Return with or without a $trailingSlash
                return $trailingSlash ? $path.DIRECTORY_SEPARATOR : $path;
            },
            \implode( ':', [...(array) $path, (int) $trailingSlash] ),
        );
    }

    /**
     * @param array<int, ?string>|string $path          the string to normalize
     * @param bool                       $trailingSlash
     *
     * @return string
     */
    public static function url(
        string|array $path,
        bool         $trailingSlash = false,
    ) : string {
        return memoize(
            function() use ( $path, $trailingSlash ) : string {
                $string = \is_array( $path ) ? \implode( '/', $path ) : $path;

                // Normalize slashes
                $string = \str_replace( ['\\', '/'], '/', $string );

                $protocol = '/';
                $fragment = '';
                $query    = '';

                // Extract and lowercase the $protocol
                if ( \str_contains( $string, '://' ) ) {
                    [$protocol, $string] = \explode( '://', $string, 2 );
                    $protocol            = \strtolower( $protocol ).'://';
                }

                // Check if the $string contains $query and $fragment
                $matchQuery    = \strpos( $string, '?' );
                $matchFragment = \strpos( $string, '#' );

                // If the $string contains both
                if ( $matchQuery && $matchFragment ) {
                    // To parse both regardless of order, we check which one appears first in the $string.
                    // Split the $string by the first $match, which will then contain the other.

                    // $matchQuery is first
                    if ( $matchQuery < $matchFragment ) {
                        [$string, $query]   = \explode( '?', $string, 2 );
                        [$query, $fragment] = \explode( '#', $query, 2 );
                    }
                    // $matchFragment is first
                    else {
                        [$string, $fragment] = \explode( '#', $string, 2 );
                        [$fragment, $query]  = \explode( '?', $fragment, 2 );
                    }

                    // After splitting, prepend the relevant identifiers.
                    $query    = "?{$query}";
                    $fragment = "#{$fragment}";
                }
                // If the $string only contains $query
                elseif ( $matchQuery ) {
                    [$string, $query] = \explode( '?', $string, 2 );
                    $query            = "?{$query}";
                }
                // If the $string only contains $fragment
                elseif ( $matchFragment ) {
                    [$string, $fragment] = \explode( '#', $string, 2 );
                    $fragment            = "#{$fragment}";
                }

                // Remove duplicate separators, and lowercase the $path
                $path = \strtolower( \implode( '/', \array_filter( \explode( '/', $string ) ) ) );

                // Prepend trailing separator if needed
                if ( $trailingSlash ) {
                    $path .= '/';
                }

                // Assemble the URL
                return $protocol.$path.$query.$fragment;
            },
            \implode( ':', [...(array) $path, (int) $trailingSlash] ),
        );
    }
}
