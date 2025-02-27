<?php

declare(strict_types=1);

namespace Support;

use JetBrains\PhpStorm\Deprecated;
use Stringable;
use RuntimeException;

final class Escape
{
    /**
     * Escapes specified substrings in a string with a `\`.
     *
     * Normalizes consecutive backslashes to a single backslash.
     *
     * @param string $string
     * @param string ...$escape
     *
     * @return string
     */
    public static function string( string $string, string ...$escape ) : string
    {
        foreach ( $escape as $substring ) {
            // Skip empty strings
            if ( ! $substring ) {
                continue;
            }

            $string = \str_replace( $substring, '\\'.$substring, $string );
        }

        if ( \str_contains( $string, '\\\\' ) ) {
            return (string) \preg_replace( '#\\\\+#', '\\', $string );
        }

        return $string;
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
    public static function each( string $string ) : string
    {
        $eachCharacter = \str_split( $string );

        if ( ! $eachCharacter ) {
            return $string;
        }

        $escaped = \array_map( static fn( $char ) => '\\'.$char, $eachCharacter );

        $string = \implode( '', $escaped );

        if ( \str_contains( $string, '\\\\' ) ) {
            return (string) \preg_replace( '#\\\\+#', '\\', $string );
        }

        return $string;
    }

    /**
     * Filter a string assuming it a URL.
     *
     * - Preserves Unicode characters.
     * - Removes tags by default.
     *
     * @param null|string|Stringable $string       $string
     * @param bool                   $preserveTags [false]
     *
     * @return string
     */
    #[Deprecated]
    public static function url(
        null|string|Stringable $string,
        bool                   $preserveTags = false,
    ) : string {
        if ( ! $string = (string) $string ) {
            return $string;
        }

        $safeCharacters = URL_SAFE_CHARACTERS_UNICODE;

        if ( $preserveTags ) {
            $safeCharacters .= '{}|^`"><@';
        }

        $filtered = (string) ( \preg_replace(
            pattern     : "/[^{$safeCharacters}]/u",
            replacement : EMPTY_STRING,
            subject     : $string,
        ) ?? EMPTY_STRING );

        // Escape special characters including tags
        return \htmlspecialchars( $filtered, ENT_QUOTES, 'UTF-8' );
    }

    /**
     * Escapes string for use inside HTML attribute value.
     *
     * @param string|Stringable $string
     * @param bool              $double
     * @param string            $encoding
     *
     * @return string
     */
    public static function elementAttribute(
        string|Stringable $string,
        bool              $double = true,
        string            $encoding = 'UTF-8',
    ) : string {
        if ( ! $string = (string) $string ) {
            return $string;
        }

        if ( \str_contains( $string, '`' ) && \strpbrk( $string, ' <>"\'' ) === false ) {
            $string .= ' '; // protection against innerHTML mXSS vulnerability nette/nette#1496
        }

        $string = \htmlspecialchars( $string, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, $encoding, $double );

        return \str_replace( '{', '&#123;', $string );
    }

    /**
     * Escapes string using {@see \htmlentities}.
     *
     * @param string|Stringable $string
     * @param non-empty-string  $encoding
     *
     * @return string
     *
     * @TODO Merge with {@see filterHtml()}.
     */
    #[Deprecated]
    public static function html( string|Stringable $string, string $encoding = 'UTF-8' ) : string
    {
        if ( ! $string = (string) $string ) {
            return $string;
        }

        return \htmlentities( $string, ENT_QUOTES | ENT_HTML5, $encoding );
    }

    /**
     * Escapes variables for use inside <script>.
     *
     * @param mixed $value
     *
     * @return string
     */
    public static function js( mixed $value ) : string
    {
        $json = \json_encode( $value, ENCODE_ESCAPE_JSON );
        if ( \json_last_error() ) {
            throw new RuntimeException( \json_last_error_msg() );
        }

        if ( ! $json ) {
            return EMPTY_STRING;
        }

        return \str_replace( [']]>', '<!', '</'], [']]\u003E', '\u003C!', '<\/'], $json );
    }

    /**
     * Escapes string for use inside CSS template.
     *
     * @param string|Stringable $string
     *
     * @return string
     *
     * @see http://www.w3.org/TR/2006/WD-CSS21-20060411/syndata.html#q6 W3C CSS Characters and case reference
     */
    public static function css( string|Stringable $string ) : string
    {
        if ( ! $string = (string) $string ) {
            return $string;
        }
        // http://www.w3.org/TR/2006/WD-CSS21-20060411/syndata.html#q6
        return \addcslashes( $string, "\x00..\x1F!\"#$%&'()*+,./:;<=>?@[\\]^`{|}~" );
    }
}
