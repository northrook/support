<?php

declare(strict_types=1);

namespace Support;

use JetBrains\PhpStorm\Deprecated;
use SplFileInfo, Override;
use Symfony\Component\Filesystem\Exception\IOException;
use Stringable;
use InvalidArgumentException;
use RuntimeException;

#[Deprecated( 'Use \Core\Pathfinder\Path instead.' )]
class FileInfo extends SplFileInfo
{
    public function __construct( string|SplFileInfo|Stringable $filename )
    {
        $string = (string) $filename;
        if ( ! \str_contains( $string, '://' ) ) {
            $string = Normalize::path( $string );
        }
        parent::__construct( $string );
    }

    final public function append( string|Stringable $string ) : FileInfo
    {
        $path = \str_ends_with( $this->getPathname(), DIRECTORY_SEPARATOR )
                ? $this->getPathname().$string
                : $this->getPathname().DIRECTORY_SEPARATOR.$string;
        return new self( $path );
    }

    /**
     * Returns the `filename` without the extension.
     *
     * @return string
     */
    #[Override]
    final public function getFilename() : string
    {
        return \strrchr( parent::getFilename(), '.', true ) ?: parent::getFilename();
    }

    final public function isUrl( ?string $protocol = null ) : bool
    {
        return isUrl( $this->getPathname(), $protocol );
    }

    final public function isPath() : bool
    {
        return isPath( $this->getPathname() );
    }

    #[Override]
    final public function isReadable() : bool
    {
        if ( $this->isUrl() ) {
            $isReadable = CURL::exists( $this->getPathname(), $error );
            if ( $error ) {
                throw new InvalidArgumentException( $error );
            }
            return $isReadable;
        }

        return parent::isReadable();
    }

    final public function isDotFile() : bool
    {
        return \str_starts_with( $this->getBasename(), '.' ) && $this->isFile();
    }

    final public function isDotDirectory() : bool
    {
        return \str_contains( $this->getPath(), DIRECTORY_SEPARATOR.'.' );
    }

    final public function getContents( bool $throwOnError = false ) : ?string
    {
        if ( $this->isUrl() ) {
            if ( $throwOnError ) {
                $message = $this::class.'::getContents() only supports local files.';
                $instead = 'Use '.CURL::class.'::fetch() instead.';
                throw new InvalidArgumentException( "{$message} {$instead}" );
            }
            return null;
        }

        $contents = \file_get_contents( $this->getPathname() );

        if ( $contents === false && $throwOnError ) {
            throw new RuntimeException( 'Unable to read file: '.$this->getPathname() );
        }

        return $contents ?: null;
    }

    final public function exists( bool $throwOnError = false ) : bool
    {
        $exists = \file_exists( $this->getPathname() );

        if ( $exists === false && $throwOnError ) {
            throw new RuntimeException( 'Unable to read file: '.$this->getPathname() );
        }

        return $exists;
    }

    /**
     * Atomically dumps content into a file.
     *
     * - {@see IOException} will be caught and logged as an error, returning false
     *
     * @param resource|string $content The data to write into the file
     *
     * @return bool True if the file was written to, false if it already existed or an error occurred
     */
    final public function save( mixed $content ) : bool
    {
        return Filesystem::save( $this->getPathname(), $content );
    }

    final public function mkdir( int $mode = 0777 ) : bool
    {
        return Filesystem::mkdir( $this->getPathname(), $mode );
    }

    /**
     * Perform one or more `glob(..)` patterns on {@see self::getPathname()}.
     *
     * Each matched result is `normalized`.
     *
     * @param string|string[] $pattern
     * @param int             $flags
     * @param bool            $asFileInfo
     *
     * @return ($asFileInfo is true ? FileInfo[] : string[]))
     */
    final public function glob(
        string|array $pattern,
        ?int         $flags = AUTO,
        bool         $asFileInfo = false,
    ) : array {
        $flags = GLOB_NOSORT | GLOB_BRACE;
        $path  = \rtrim( $this->getPathname(), '\\/' );
        $glob  = [];

        foreach ( (array) $pattern as $match ) {
            $match = \DIRECTORY_SEPARATOR.\ltrim( $match, '\\/' );
            $glob  = [...$glob, ...( \glob( $path.$match, $flags ) ?: [] )];
        }

        if ( $asFileInfo ) {
            return \array_map( FileInfo::from( ... ), $glob );
        }

        return \array_map( Normalize::path( ... ), $glob );
    }

    /**
     * Sets access and modification time of file.
     *
     * @param ?int $time  The touch time as a Unix timestamp, if not supplied the current system time is used
     * @param ?int $atime The access time as a Unix timestamp, if not supplied the current system time is used
     *
     * @return bool
     */
    final public function touch( ?int $time = null, ?int $atime = null ) : bool
    {
        return Filesystem::touch( $this->getPathname(), $time, $atime );
    }

    /**
     * Copies {@see self::getRealPath()} to {@see $targetFile}.
     *
     * - If the target file is automatically overwritten when this file is newer.
     * - If the target is newer, $overwriteNewerFiles decides whether to overwrite.
     * - {@see IOException}s will be caught and logged as an error, returning false
     *
     * @param string $targetFile
     * @param bool   $overwriteNewerFiles
     *
     * @return bool True if the file was written to, false if it already existed or an error occurred
     */
    final public function copy( string $targetFile, bool $overwriteNewerFiles = false ) : bool
    {
        return Filesystem::copy( $this->getPathname(), $targetFile, $overwriteNewerFiles );
    }

    /**
     * Remove {@see self}.
     *
     * @return bool
     */
    final public function remove() : bool
    {
        return Filesystem::remove( $this->getPathname() );
    }

    final public static function from( string|SplFileInfo|Stringable $filename ) : self
    {
        return new static( $filename );
    }
}
