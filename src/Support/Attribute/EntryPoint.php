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
    /** @var callable-string[]|class-string[]|string[] */
    public array $usedBy;

    /**
     * @param callable-string|class-string|string ...$usedBy
     */
    public function __construct( string ...$usedBy )
    {
        $this->usedBy = $usedBy;
    }
}
