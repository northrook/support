<?php

declare(strict_types=1);

namespace Support;

use SplFileInfo, Override;

class FileInfo extends SplFileInfo
{
    /**
     * Returns the `filename` without the extension.
     *
     * @return string
     */
    #[Override]
    final public function getFilename() : string
    {
        return \strstr( parent::getFilename(), '.', true ) ?: parent::getFilename();
    }

    final public function getContents() : ?string
    {
        $contents = \file_get_contents( $this->getPathname() );

        if ( false === $contents ) {
            return null;
        }

        return $contents;
    }
}
