<?php

declare(strict_types=1);

namespace Support;

use Stringable;

final class Trim
{
    /**
     * @param null|string|Stringable $string
     *
     * @return string
     */
    public static function whitespace( string|Stringable|null $string ) : string
    {
        return (string) \preg_replace( '#\s+#', ' ', \trim( (string) $string ) );
    }
}
