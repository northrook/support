<?php

declare(strict_types=1);

namespace Support\Interface;

/**
 * Indicate that the implementing class is handling data by reference.
 */
interface ProxyInterface
{
    public static function byReference( mixed &$data ) : self;
}
