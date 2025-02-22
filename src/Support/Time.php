<?php

declare(strict_types=1);

namespace Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Stringable;

/**
 * @author  Martin Nielsen <mn@northrook.com>
 */
final readonly class Time implements Stringable
{
    public const string FORMAT_SORTABLE = 'Y-m-d H:i:s';

    public const string FORMAT_HUMAN = 'd-m-Y H:i:s';

    public const string FORMAT_W3C = 'Y-m-d\TH:i:sP';

    public const string FORMAT_RFC3339 = 'Y-m-d\TH:i:sP';

    public const string FORMAT_RFC3339_EXTENDED = 'Y-m-d\TH:i:s.vP';

    private DateTimeImmutable $dateTimeImmutable;

    public int $unixTimestamp;

    public string $datetime;

    public string $timezone;

    public function __construct(
        int|string|DateTimeInterface $dateTime = 'now',
        string|DateTimeZone          $timezone = 'UTC',
        string                       $format = Time::FORMAT_SORTABLE,
    ) {
        $this->dateTimeImmutable = timestamp( $dateTime, $timezone );

        $this->unixTimestamp = $this->dateTimeImmutable->getTimestamp();
        $this->timezone      = $this->dateTimeImmutable->getTimezone()->getName();
        $this->datetime      = $this->dateTimeImmutable->format( $format ).' '.$this->timezone;
    }

    /**
     * @param DateTimeZone|string $timezone
     * @param string              $format
     *
     * @return Time
     */
    public static function now(
        string|DateTimeZone $timezone = 'UTC',
        string              $format = Time::FORMAT_SORTABLE,
    ) : Time {
        return new self( 'now', $timezone, $format );
    }

    final public function format( string $format, bool|string $wrapEach = false ) : string
    {
        if ( $wrapEach ) {
            $prefixClass = \is_string( $wrapEach ) ? $wrapEach : 'datetime';
            $format      = $this->wrapFormatted( $format, $prefixClass );
        }

        return $this->dateTimeImmutable->format( $format );
    }

    final public function __toString() : string
    {
        return $this->datetime;
    }

    public static function getTimezone( null|string|DateTimeZone $timezone = null ) : DateTimeZone
    {
        if ( $timezone instanceof DateTimeZone ) {
            return $timezone;
        }

        try {
            return new DateTimeZone( $timezone ?? \date_default_timezone_get() );
        }
        catch ( Exception $exception ) {
            \error_log( $exception->getMessage() );
            return new DateTimeZone( 'UTC' );
        }
    }

    private function wrapFormatted( string $string, ?string $classPrefix = null ) : string
    {
        $each = [];

        $string = (string) \preg_replace_callback_array(
            [
                // Day
                '#[dD]#' => static function( $match ) use ( &$each ) {
                    $each[] = [
                        'type' => 'day',
                        'flag' => $match[0],
                    ];
                    return '['.( \count( $each ) - 1 ).']';
                },
                // Month
                '#[mM]#' => static function( $match ) use ( &$each ) {
                    $each[] = [
                        'type' => 'month',
                        'flag' => $match[0],
                    ];
                    return '['.( \count( $each ) - 1 ).']';
                },
                // Year
                '#[yY]#' => static function( $match ) use ( &$each ) {
                    $each[] = [
                        'type' => 'year',
                        'flag' => $match[0],
                    ];
                    return '['.( \count( $each ) - 1 ).']';
                },
                // Day
                '#[jS]#' => static function( $match ) use ( &$each ) {
                    $each[] = [
                        'type' => 'day',
                        'flag' => $match[0],
                    ];
                    return '['.( \count( $each ) - 1 ).']';
                },
                // Weekday
                '#W#' => static function( $match ) use ( &$each ) {
                    $each[] = [
                        'type' => 'weekday',
                        'flag' => $match[0],
                    ];
                    return '['.( \count( $each ) - 1 ).']';
                },
                // Time
                '#[aABgGhHisu].*#' => static function( $match ) use ( &$each ) {
                    $each[] = [
                        'type' => 'time',
                        'flag' => $match[0],
                    ];
                    return '['.( \count( $each ) - 1 ).']';
                },
            ],
            $string,
        );

        foreach ( $each as $key => $value ) {
            $class  = \implode( '-', [$classPrefix, $value['type']] );
            $flag   = $value['flag'];
            $string = \str_replace(
                "[{$key}]",
                $this->esc( '<span class="'.$class.'">' ).$flag.$this->esc( '</span>' ),
                $string,
            );
        }

        return $string;
    }

    /**
     * Escape each and every character in the provided string.
     *
     * ```
     * escapeCharacters('Hello!');
     * // => '\H\e\l\l\o\!'
     * ```
     *
     * @param string $string
     *
     * @return string
     */
    private function esc( string $string ) : string
    {
        return \implode( '', \array_map( static fn( $char ) => '\\'.$char, \str_split( $string ) ) );
    }
}
