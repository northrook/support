<?php

declare(strict_types=1);

namespace Support;

use JetBrains\PhpStorm\Deprecated;
use Stringable;

#[Deprecated]
final class Trim
{
    /**
     * @param null|string|Stringable $string
     *
     * @return string
     */
    #[Deprecated( 'Not really trimming, is it?', '\Support\Normalize::whitespace' )]
    public static function whitespace( string|Stringable|null $string ) : string
    {
        return (string) \preg_replace( '#\s+#', ' ', \trim( (string) $string ) );
    }
}
