<?php

declare(strict_types=1);

namespace Support;

use SplFileInfo, Override;

final class FileInfo extends SplFileInfo
{
    /**
     * Returns the `filename` without the extension.
     *
     * @return string
     */
    #[Override]
    public function getFilename() : string
    {
        return \strstr( parent::getFilename(), '.', true ) ?: parent::getFilename();
    }
}
