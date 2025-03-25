<?php

declare(strict_types=1);

namespace Support;

use InvalidArgumentException;
use JetBrains\PhpStorm\Deprecated;

final class Num
{
    #[Deprecated( 'No alternative as of yet.' )]
    public static function pad( int|float $number, int|string $pad, bool $prepend = false ) : int|string
    {
        trigger_deprecation(
            'Support\Num',
            '_dev',
            __METHOD__.' deprecated',
        );
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
    #[Deprecated( 'Use Support\num_byte_size() instead.' )]
    public static function byteSize( string|int|float $bytes ) : string
    {
        trigger_deprecation(
            'Support\Num',
            '_dev',
            __METHOD__.' deprecated',
        );
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
