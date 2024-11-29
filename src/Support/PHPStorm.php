<?php

declare(strict_types=1);

namespace Support;

final class PHPStorm
{
    public static function stringArgument(
        callable   $functionReference,
        int        $argumentIndex,
        ?string ...$values,
    ) : bool {
        [$class, $method] = explode_class_callable( $functionReference );
        $file             = '.'.Normalize::key( $class, '_' ).'meta.php';
        $path             = \Support\getProjectRootDirectory( ".phpstorm.meta.php/{$file}" );

        $meta = "'".\implode( "', '", $values )."'";
        return false;
    }
}
