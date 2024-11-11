<?php

declare(strict_types=1);

namespace Exception;

use BadMethodCallException, Throwable;

class NotImplementedException extends BadMethodCallException
{
    public function __construct(
        public readonly string $class,
        public readonly string $interface,
        ?string                $message = null,
        ?Throwable             $previous = null,
    ) {
        parent::__construct( $this->generateMessage( $message ), 500, $previous );
    }

    private function generateMessage( ?string $message = null ) : string
    {
        $exists = \class_exists( $this->interface );

        return $message ?? match ( true ) {
            ! $exists => "The class for the interface '{$this->interface}' does not exist.",
            default   => "The {$this->class} does not implement the '{$this->interface}' interface.",
        };
    }
}
