<?php

declare(strict_types=1);

namespace Support;

use InvalidArgumentException;

final class Num
{
    /**
     * @param float $number
     * @param float $min
     * @param float $max
     *
     * @return bool
     */
    public static function within( float $number, float $min, float $max ) : bool
    {
        return $number >= $min && $number <= $max;
    }

    /**
     * @param float $number
     * @param float $min
     * @param float $max
     * @param bool  $isWithin
     *
     * @return float
     */
    public static function clamp( float $number, float $min, float $max, bool &$isWithin ) : float
    {
        $isWithin = Num::within( $number, $min, $max );

        return \max( $min, \min( $number, $max ) );
    }

    /**
     * @see https://stackoverflow.com/questions/5464919/find-a-matching-or-closest-value-in-an-array stackoverflow
     *
     * @param int   $humber
     * @param int[] $in
     * @param bool  $returnKey
     *
     * @return null|int|string
     */
    public static function closest( int $humber, array $in, bool $returnKey = false ) : string|int|null
    {
        foreach ( $in as $key => $value ) {
            if ( $humber <= $value ) {
                return $returnKey ? $key : $value;
            }
        }

        return null;
    }

    /**
     * @param float $from
     * @param float $to
     *
     * @return float
     */
    public static function percentDifference( float $from, float $to ) : float
    {
        if ( ! $from || $from === $to ) {
            return 0;
        }
        return (float) \number_format( ( $from - $to ) / $from * 100, 2 );
    }

    public static function pad( int|float $number, int|string $pad, bool $prepend = false ) : int|string
    {
        $float = \is_float( $number );

        $number = (string) $number;
        $pad    = (string) $pad;

        if ( ! \ctype_digit( $pad ) ) {
            throw new InvalidArgumentException( 'Pad must be a number.' );
        }

        $length = \strlen( $number.$pad );
        $type   = ( $prepend ? STR_PAD_LEFT : STR_PAD_RIGHT );

        $number = \str_pad( $number, $length, $pad, $type );

        return $float ? $number : (int) $number;
    }

    /**
     * Human-readable size notation for a byte value.
     *
     * @link https://en.wikipedia.org/wiki/Byte#Multiple-byte_units Wikipedia - Multiple-byte units
     *
     * @param float|int|string $bytes Bytes to calculate
     *
     * @return string
     */
    public static function byteSize( string|int|float $bytes ) : string
    {
        $bytes = (float) ( \is_string( $bytes ) ? \mb_strlen( $bytes, '8bit' ) : $bytes );

        $unitDecimalsByFactor = [
            ['B', 0],  //     byte
            ['KiB', 0], // kibibyte
            ['MiB', 2], // mebibyte
            ['GiB', 2], // gigabyte
            ['TiB', 3], // mebibyte
            ['PiB', 3], // mebibyte
        ];

        $factor = $bytes ? \floor( \log( (int) $bytes, 1_024 ) ) : 0;
        $factor = (float) \min( $factor, \count( $unitDecimalsByFactor ) - 1 );

        $value = \round( $bytes / ( 1_024 ** $factor ), (int) $unitDecimalsByFactor[$factor][1] );
        $units = (string) $unitDecimalsByFactor[$factor][0];

        return $value.$units;
    }
}
