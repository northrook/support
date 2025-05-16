<?php

declare(strict_types=1);

namespace Support;

use JetBrains\PhpStorm\{Deprecated, Language};
use Stringable;

class Str implements Stringable
{
    public const int    TAB_SIZE = 4;

    public const string ENCODING = 'UTF-8';

    private string $string;

    public function __construct( string $string )
    {
        $this->string = str_encode( $string, self::ENCODING );
    }

    public function __toString()
    {
        return $this->string;
    }

    /**
     * @param string $pattern
     * @param string $string
     *
     * @return ?string
     */
    #[Deprecated]
    public static function extract(
        #[Language( 'RegExp' )]
        string $pattern,
        string $string,
    ) : ?string {
        trigger_deprecation(
            'Support\Str',
            '_dev',
            __METHOD__.' deprecated',
        );
        if ( \preg_match_all( $pattern, $string, $matches, PREG_SET_ORDER ) === false ) {
            return null;
        }

        return $matches[0][0] ?? null;
    }

    /**
     * @param string $pattern
     * @param string $subject
     * @param int    $offset
     * @param int    $count
     *
     * @return array<int, array<null|string>>
     */
    #[Deprecated]
    public static function extractNamedGroups(
        string $pattern,
        string $subject,
        int    $offset = 0,
        int &    $count = 0,
    ) : array {
        trigger_deprecation(
            'Support\Str',
            '_dev',
            __METHOD__.' deprecated',
        );
        \preg_match_all( $pattern, $subject, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL, $offset );

        foreach ( $matches as $index => $match ) {
            $getNamed = static fn( $value, $key ) => \is_string( $key ) ? $value : false;
            $named    = arr_filter( $match, $getNamed, Arr::USE_BOTH );

            if ( $named ) {
                $matches[$index] = ['match' => \array_shift( $match ), ...$named];
            }
            else {
                unset( $matches[$index] );
            }
        }

        $count += \count( $matches );

        return $matches;
    }

    /**
     * @param null|string|Stringable $string
     * @param non-empty-string       $separator
     * @param int                    $limit
     * @param bool                   $filter
     *
     * @return string[]
     */
    #[Deprecated]
    public static function explode(
        null|string|Stringable $string,
        string                 $separator = ',',
        int                    $limit = PHP_INT_MAX,
        bool                   $filter = true,
    ) : array {
        trigger_deprecation(
            'Support\Str',
            '_dev',
            __METHOD__.' deprecated',
        );
        $exploded = \explode( $separator, as_string( $string ), $limit );

        return $filter ? arr_filter( $exploded ) : $exploded;
    }

    /**
     * mix of stringStartsWith, stringEndsWith, stringContains.
     *
     * ```
     * function stringContains(
     *     bool caseSensitive = false,
     * ) : bool | int | array | string
     * {
     *   foreach ( (array) $substring as $substring ) {
     *     if ( \%%str_CALLBACK%%( $string, $caseSensitive ? $substring : \strtolower( $substring ) ) ) {
     *       return true;
     *     }
     *   }
     * }
     *```
     *
     * @param string   $string
     * @param string[] $needle
     * @param bool     $returnNeedles
     * @param bool     $containsOnlyOne
     * @param bool     $containsAll
     * @param bool     $caseSensitive
     *
     * @return bool|int|string|string[]
     */
    #[Deprecated]
    public static function contains(
        string       $string,
        string|array $needle,
        bool         $returnNeedles = false,
        bool         $containsOnlyOne = false,
        bool         $containsAll = false,
        bool         $caseSensitive = false,
    ) : bool|int|array|string {
        trigger_deprecation(
            'Support\Str',
            '_dev',
            __METHOD__.' deprecated',
        );
        $count    = 0;
        $contains = [];

        $find = static fn( string $string ) => $caseSensitive ? $string : \strtolower( $string );

        $string = $find( $string );

        if ( \is_string( $needle ) ) {
            $count = \substr_count( $string, $find( $needle ) );
        }
        else {
            foreach ( $needle as $index => $value ) {
                $match = \substr_count( $string, $find( $value ) );
                if ( $match ) {
                    $contains[] = $value;
                    $count += $match;
                    unset( $needle[$index] );
                }
            }
        }

        if ( $containsOnlyOne && \count( $contains ) !== 1 ) {
            return false;
        }

        if ( $containsAll && empty( $needle ) ) {
            return true;
        }

        if ( $returnNeedles ) {
            return ( \count( (array) $needle ) === 1 ) ? \reset( $contains ) : $contains;
        }

        return $count;
    }

    #[Deprecated( replacement : '\Support\Escape::string()' )]
    public static function escape( string $string, string ...$escape ) : string
    {
        trigger_deprecation(
            'Support\Str',
            '_dev',
            __METHOD__.' deprecated',
        );

        foreach ( $escape as $substring ) {
            $string = \str_replace( $substring, '\\'.$substring, $string );
        }

        if ( \str_contains( $string, '\\\\' ) ) {
            return (string) \preg_replace( '#\\\\+#', '\\', $string );
        }

        return $string;
    }

    /**
     * Escape every character in the provided string.
     *
     * ```
     *  escapeCharacters('Hello!');
     *  // => '\H\e\l\l\o\!'
     * ```
     *
     * @param string $string
     *
     * @return string
     */
    #[Deprecated( replacement : '\Support\Escape::each()' )]
    public static function eascapeEach( string $string ) : string
    {
        trigger_deprecation(
            'Support\Str',
            '_dev',
            __METHOD__.' deprecated',
        );
        return \implode( '', \array_map( static fn( $char ) => '\\'.$char, \str_split( $string ) ) );
    }
}
