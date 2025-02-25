<?php

declare(strict_types=1);

namespace Support;

use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
final class Character
{
    public static function isDelimiter( string $string ) : bool
    {
        return (bool) \preg_match( '#^[,;]+$#', $string );
    }

    public static function isPunctuation( string $string, bool $endingOnly = false ) : bool
    {
        return (bool) ( $endingOnly
                ? \preg_match( '#^[.!]+$#', $string )
                : \preg_match( '#^[[:punct:]]+$#', $string ) );
    }
}
