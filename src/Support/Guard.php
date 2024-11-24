<?php

namespace Support;

use LengthException;

final class Guard
{
    /**
     * @param string $string    The string to check
     * @param ?int   $maxLength [PHP_MAXPATHLEN-2]
     * @param string $message   $maxLength provided as %s
     *
     * @return void
     *
     * @throws LengthException when the $path exceeds $maxPathLength
     */
    public static function maxLength(
        string $string,
        ?int   $maxLength = null,
        string $message = 'The provided string exceeds the maximum length of %s.',
    ) : void {
        $maxLength ??= PHP_MAXPATHLEN - 2;
        if ( \strlen( $string ) > $maxLength ) {
            throw new LengthException( \sprintf( $message, $maxLength ), 500 );
        }
    }
}
