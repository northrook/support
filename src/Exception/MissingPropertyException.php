<?php

declare(strict_types=1);

namespace Exception;

use LogicException, Throwable;

class MissingPropertyException extends LogicException
{
    /**
     * @param string         $property
     * @param class-string   $class
     * @param null|string    $message
     * @param int            $code
     * @param null|Throwable $previous
     */
    public function __construct(
        public readonly string $property,
        public readonly string $class,
        ?string                $message = null,
        int                    $code = 500,
        ?Throwable             $previous = null,
    ) {
        $message ??= "Property '{$this->property}' does not exist in '{$this->class}'.";
        parent::__construct( $message, $code, $previous );
    }
}
