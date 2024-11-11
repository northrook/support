<?php

declare(strict_types=1);

namespace Interface;

use Stringable;

/**
 */
interface Printable extends Stringable
{
    /**
     * Prepare a string of HTML for front-end use.
     *
     * Must handle all parsing, optimization, escaping, and encoding.
     *
     * Strings returned will be regarded as safe for front-end use.
     *
     * @used-by __toString()
     *
     * @return ?string
     */
    public function toString() : ?string;

    /**
     * Echo the resulting HTML, or nothing if the element does not pass validation.
     *
     * ⚠️ Assumes the {@see __toString()} handles all parsing, optimization, escaping, and encoding.
     *
     *  ```
     * // escape strings, optimize, etc
     * public function __toString() : string {
     *    return trim( $this->build() )
     * }
     *
     * // print the resulting HTML
     * public function print() : void {
     *    return $this->__toString();
     * }
     *  ```
     */
    public function print() : void;
}
