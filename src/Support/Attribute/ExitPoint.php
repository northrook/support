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
    /** @var callable-string[]|class-string[]|string[] */
    public array $usedBy;

    /**
     * @param callable-string|class-string|string ...$consumedBy
     */
    public function __construct( string ...$consumedBy )
    {
        $this->usedBy = $consumedBy;
    }
}
