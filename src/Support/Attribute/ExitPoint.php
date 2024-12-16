<?php

declare(strict_types=1);

namespace Support\Attribute;

use Attribute;

/**
 * Indicate that this `function` or `method` is the **final step** in a process.
 */
#[Attribute( Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD )]
final readonly class ExitPoint
{
    /** @var list<array{0: class-string, 1: string}>|string[] */
    public array $consumedBy;

    /**
     * @param array{0: class-string, 1: string}|string ...$consumedBy
     */
    public function __construct( string|array ...$consumedBy )
    {
        $this->consumedBy = $consumedBy;
    }
}
