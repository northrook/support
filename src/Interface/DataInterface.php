<?php

declare(strict_types=1);

namespace Interface;

/**
 * Indicates that this class is a `DataTransferObject`.
 *
 * The class must follow these rules:
 * - All properties are `public`.
 * - {@see self::__construct} ingests typed arguments.
 * - No internal logic outside of handling ingested data.
 *
 * The class may add `get` methods for conditional property retrieval.
 *
 * The {@see self::READONLY} indicates the `readonly` status.
 *
 * @author Martin Nielsen <mn@northrook.com>
 */
interface DataInterface
{
    public const bool READONLY = false;
}