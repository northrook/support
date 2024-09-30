<?php

declare(strict_types=1);

namespace Support;

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
     * @param float     $number
     * @param float     $min
     * @param float     $max
     * @param null|bool $isWithin
     *
     * @return float
     */
    public static function clamp( float $number, float $min, float $max, bool &$isWithin = null ) : float
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

    /**
     * Human-readable size notation for a byte value.
     *
     * @param float|int|string $bytes Bytes to calculate
     *
     * @return string
     */
    public static function byteSize( string|int|float $bytes ) : string
    {
        if ( ! \is_numeric( $bytes ) ) {
            $type  = \gettype( $bytes );
            $value = \print_r( $bytes, true );
            throw new \InvalidArgumentException( __METHOD__." only accepts string, int, or float.\n'{$type}' of '{$value}' provided." );
        }

        $bytes                = (float) $bytes;

        $unitDecimalsByFactor = [
            ['B', 0],
            ['kB', 0],
            ['MB', 2],
            ['GB', 2],
            ['TB', 3],
            ['PB', 3],
        ];

        $factor               = $bytes ? \floor( \log( (int) $bytes, 1_024 ) ) : 0;
        $factor               = (float) \min( $factor, \count( $unitDecimalsByFactor ) - 1 );

        $value                = \round( $bytes / ( 1_024 ** $factor ), (int) $unitDecimalsByFactor[$factor][1] );
        $units                = (string) $unitDecimalsByFactor[$factor][0];

        return $value.$units;
    }
}
