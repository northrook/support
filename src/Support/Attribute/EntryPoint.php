<?php

declare(strict_types=1);

namespace Support\Attribute;

use Attribute;

/**
 * Indicate that this `function` or `method` is the **first step** in a process.
 */
#[Attribute( Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD )]
final readonly class EntryPoint
{
    /** @var list<array{0: class-string, 1: string}>|string[] */
    public array $usedBy;

    /**
     * @param array{0: class-string, 1: string}|string ...$usedBy
     */
    public function __construct( string|array ...$usedBy )
    {
        $this->usedBy = $usedBy;
    }
}
