<?php

declare(strict_types=1);

namespace Support;

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
     * @param ?string[] $string
     * @param string    $separator                = ['-', '_', ''][$any]
     * @param int       $characterLimit
     * @param bool      $throwOnIllegalCharacters
     *
     * @return string
     */
    public static function key(
        string|array|null $string,
        string $separator = '-',
        int $characterLimit = 0,
        bool $throwOnIllegalCharacters = false,
    ) : string {
        // Convert to lowercase
        $string = \strtolower( \is_string( $string ) ? $string : \implode( $separator, $string ) );

        // Enforce characters
        if ( $throwOnIllegalCharacters && ! \preg_match( "/^[a-zA-Z0-9_\-{$separator}]+$/", $string ) ) {
            throw new \InvalidArgumentException( 'The provided string contains illegal characters. It must only accept ASCII letters, numbers, hyphens, and underscores.' );
        }

        // Replace non-alphanumeric characters with the separator
        $string = (string) \preg_replace( "/[^a-z0-9{$separator}]+/i", $separator, $string );

        if ( $characterLimit && \strlen( $string ) >= $characterLimit ) {
            throw new \InvalidArgumentException( "The normalized key string exceeds the maximum length of '{$characterLimit}' characters." );
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
     * - Will throw a {@see \LengthException} if the resulting string exceeds {@see PHP_MAXPATHLEN}.
     *
     * ```
     * normalizePath( './assets\\\/scripts///example.js' );
     * // => '.\assets\scripts\example.js'
     * ```
     *
     * @param string[] $string        the string to normalize
     * @param bool     $trailingSlash append a trailing slash
     */
    public static function path(
        string|array $string,
        bool $trailingSlash = false,
    ) : string {
        static $cache = [];

        return $cache[\json_encode( [$string, $trailingSlash], 832 )] ??= (
            static function() use ( $string, $trailingSlash ) : string {
                // Normalize separators
                $normalize = \str_replace( ['\\', '/'], DIRECTORY_SEPARATOR, $string );

                // Explode strings for separator deduplication
                $exploded  = \is_string( $normalize ) ? \explode( DIRECTORY_SEPARATOR, $normalize ) : $normalize;

                // Ensure each part does not start or end with illegal characters
                $exploded  = \array_map(
                    static fn( $item ) => \trim(
                        string     : $item,
                        characters : " \n\r\t\v\0\\/",
                    ),
                    $exploded,
                );

                // Filter the exploded path, and implode using the directory separator
                $path      = \implode( DIRECTORY_SEPARATOR, \array_filter( $exploded ) );

                if ( ( $length = \strlen( $path ) ) > ( $limit = \PHP_MAXPATHLEN - 2 ) ) {
                    throw new \LengthException( __FUNCTION__." resulted in a '{$length}' character string, exceeding the '{$limit}' limit." );
                }

                // Add to realpath cache if valid
                $path      = \realpath( $path ) ?: $path;

                // Return with or without a $trailingSlash
                return $trailingSlash ? $path.DIRECTORY_SEPARATOR : $path;
            }
        )();
    }

    /**
     * @param string[] $string        $string
     * @param bool     $trailingSlash
     *
     * @return string
     */
    public static function url(
        string|array $string,
        bool $trailingSlash = false,
    ) : string {
        static $cache = [];

        return $cache[\json_encode( [$string, $trailingSlash], 832 )] ??= (
            static function() use ( $string, $trailingSlash ) : string {
                $string        = \is_array( $string ) ? \implode( '/', $string ) : $string;

                $protocol      = '/';
                $fragment      = '';
                $query         = '';

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
                $path          = \strtolower( \implode( '/', \array_filter( \explode( '/', $string ) ) ) );

                // Prepend trailing separator if needed
                if ( $trailingSlash ) {
                    $path .= '/';
                }

                // Assemble the URL
                return $protocol.$path.$query.$fragment;
            }
        )();
    }
}
