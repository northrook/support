<?php

declare(strict_types=1);

namespace Support;

use JetBrains\PhpStorm\Language;
use Stringable;
use const ENCODING;

class Str implements Stringable
{
    public const string ENCODING = 'UTF-8';

    private string $string;

    public function __construct( string $string )
    {
        $this->string = $this::encode( $string );
    }

    public function __toString()
    {
        return $this->string;
    }

    /**
     * Ensures appropriate string encoding.
     *
     * Replacement for the deprecated {@see \mb_convert_encoding()}, see [PHP.watch](https://php.watch/versions/8.2/mbstring-qprint-base64-uuencode-html-entities-deprecated) for details.
     *
     * Directly inspired by [aleblanc](https://github.com/aleblanc)'s comment on [this GitHub issue](https://github.com/symfony/symfony/issues/44281#issuecomment-1647665965).
     *
     * @param null|string|Stringable $string
     * @param non-empty-string       $encoding
     *
     * @return string
     */
    public static function encode( null|string|Stringable $string, string $encoding = ENCODING ) : string
    {
        if ( ! $string = (string) $string ) {
            return EMPTY_STRING;
        }

        $entities = \htmlentities( $string, ENT_NOQUOTES, $encoding, false );
        $decoded  = \htmlspecialchars_decode( $entities, ENT_NOQUOTES );
        $map      = [0x80, 0x10_FF_FF, 0, ~0];

        return \mb_encode_numericentity( $decoded, $map, ENCODING );
    }

    /**
     * Compress a string by replacing consecutive whitespace characters with a single one.
     *
     * @param string $string
     * @param bool   $whitespaceOnly if true, only spaces are squished, leaving tabs and new lines intact
     *
     * @return string the squished string with consecutive whitespace replaced by the defined whitespace character
     */
    public static function squish( string $string, bool $whitespaceOnly = false ) : string
    {
        return (string) ( $whitespaceOnly
                ? \preg_replace( '# +#', WHITESPACE, $string )
                : \preg_replace( "#\s+#", WHITESPACE, $string ) );
    }

    /**
     * @param string $pattern
     * @param string $string
     *
     * @return ?string
     */
    public static function extract(
        #[Language( 'RegExp' )] string $pattern,
        string $string,
    ) : ?string {
        if ( false === \preg_match_all( $pattern, $string, $matches, PREG_SET_ORDER ) ) {
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
    public static function extractNamedGroups(
        string $pattern,
        string $subject,
        int    $offset = 0,
        int &    $count = 0,
    ) : array {
        \preg_match_all( $pattern, $subject, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL, $offset );

        foreach ( $matches as $index => $match ) {
            $getNamed = static fn( $value, $key ) => \is_string( $key ) ? $value : false;
            $named    = Arr::filter( $match, $getNamed, Arr::FILTER_BOTH );

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
    public static function explode(
        null|string|Stringable $string,
        string                 $separator = ',',
        int                    $limit = PHP_INT_MAX,
        bool                   $filter = true,
    ) : array {
        $exploded = \explode( $separator, toString( $string ), $limit );

        return $filter ? Arr::filter( $exploded ) : $exploded;
    }

    /** Replace each key from `$map` with its value, when found in `$content`.
     *
     * @template From of non-empty-string|string
     * @template To of null|string|\Stringable
     *
     * @param array<From,To> $map           [ From => To ]
     * @param string[]       $content
     * @param bool           $caseSensitive
     *
     * @return array|string|string[] The processed `$content`, or null if `$content` is empty
     */
    public static function replaceEach(
        array        $map,
        string|array $content,
        bool         $caseSensitive = true,
    ) : string|array {
        if ( ! $content ) {
            return $content;
        }

        $keys = \array_keys( $map );

        return $caseSensitive
                ? \str_replace( $keys, $map, $content )
                : \str_ireplace( $keys, $map, $content );
    }

    /**
     * @param string $string
     * @param string $substring
     * @param bool   $first
     * @param bool   $includeSubstring
     *
     * @return array{string, ?string}
     */
    public static function bisect(
        string $string,
        string $substring,
        bool   $first = true,
        bool   $includeSubstring = true,
    ) : array {
        if ( ! \str_contains( $string, $substring ) ) {
            return [$string, null];
        }

        $offset = $first ? \strpos( $string, $substring ) : \strrpos( $string, $substring );

        if ( false === $offset ) {
            \trigger_error(
                __FUNCTION__." could not split '{$substring}' using '{$substring}'.\nOffset position could not be determined.",
                E_USER_WARNING,
            );

            return [$string, null];
        }

        if ( $first ) {
            $offset = $includeSubstring ? $offset + \strlen( $substring ) : $offset;
        }
        else {
            $offset = $includeSubstring ? $offset : $offset - \strlen( $substring );
        }

        $before = \substr( $string, 0, $offset );
        $after  = \substr( $string, $offset );

        return [
            $before,
            $after,
        ];
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
    public static function contains(
        string       $string,
        string|array $needle,
        bool         $returnNeedles = false,
        bool         $containsOnlyOne = false,
        bool         $containsAll = false,
        bool         $caseSensitive = false,
    ) : bool|int|array|string {
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

    public static function after( string $string, string $substring, bool $first = false ) : string
    {
        if ( ! \str_contains( $string, $substring ) ) {
            return $string;
        }

        $offset = $first ? \strpos( $string, $substring ) : \strrpos( $string, $substring );

        if ( false === $offset ) {
            return $string;
        }
        $offset += \strlen( $substring );

        return \substr( $string, $offset );
    }

    public static function before( string $string, string $substring, bool $first = false ) : string
    {
        if ( ! \str_contains( $string, $substring ) ) {
            return $string;
        }
        $offset = $first ? \strpos( $string, $substring ) : \strrpos( $string, $substring );

        if ( false === $offset ) {
            return $string;
        }
        // else {
        //     $offset += \strlen( $substring );
        // }

        return \substr( $string, 0, $offset );
    }

    /**
     * @param string   $string
     * @param string[] $substring
     * @param bool     $caseSensitive
     *
     * @return bool
     */
    public static function startsWith( string $string, string|array $substring, bool $caseSensitive = false ) : bool
    {
        if ( ! $caseSensitive ) {
            $string = \strtolower( $string );
        }

        foreach ( (array) $substring as $substring ) {
            if ( \str_starts_with( $string, $caseSensitive ? $substring : \strtolower( $substring ) ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string   $string
     * @param string[] $substring
     * @param bool     $caseSensitive
     *
     * @return bool
     */
    public static function endsWith( string $string, string|array $substring, bool $caseSensitive = false ) : bool
    {
        if ( ! $caseSensitive ) {
            $string = \strtolower( $string );
        }

        foreach ( (array) $substring as $substring ) {
            if ( \str_ends_with( $string, $caseSensitive ? $substring : \strtolower( $substring ) ) ) {
                return true;
            }
        }

        return false;
    }

    public static function start( string $string, string $with, ?string $separator = null ) : string
    {
        if ( \str_starts_with( $string, $with ) ) {
            return $string;
        }

        return $with.$separator.$string;
    }

    public static function end( string $string, string $with, ?string $separator = null ) : string
    {
        if ( \str_ends_with( $string, $with ) ) {
            return $string;
        }

        return $string.$separator.$with;
    }

    /**
     * Escape each and every character in the provided string.
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
    public static function eascapeEach( string $string ) : string
    {
        return \implode( '', \array_map( static fn( $char ) => '\\'.$char, \str_split( $string ) ) );
    }
}
