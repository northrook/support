<?php

declare(strict_types=1);

namespace Support;

use Iterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class FileScanner
{
    /** @var FileInfo[]|string */
    private array $files = [];

    /** @var null|positive-int */
    private ?int $lastModified = null;

    /** @var null|bool|string[] */
    protected readonly null|bool|array $extension;

    /**
     * ### Extension
     * - `null` directories and all file types
     * - `true` files only
     * - `false` directories only
     * - `string[]` only matching extensions
     *
     * @param string                    $directory
     * @param null|bool|string|string[] $extension
     * @param bool                      $recursion
     * @param bool                      $dotDirectories
     * @param bool                      $dotFiles
     */
    private function __construct(
        public readonly string $directory,
        null|bool|string|array $extension = null,
        protected bool|int     $recursion = false,
        protected bool         $dotDirectories = false,
        protected bool         $dotFiles = false,
    ) {
        dump( __METHOD__, ...\debug_backtrace() );
        $this->setExtension( $extension );
    }

    /**
     * ### Extension
     * - `null` directories and all file types
     * - `true` files only
     * - `false` directories only
     * - `string[]` only matching extensions
     *
     * @param string                    $directory
     * @param null|bool|string|string[] $extension
     * @param bool                      $recursion
     * @param bool                      $dotDirectories
     * @param bool                      $dotFiles
     * @param bool                      $asString
     * @param bool                      $stringPath
     *
     * @return FileInfo[]
     */
    public static function get(
        string                 $directory,
        null|bool|string|array $extension = null,
        bool|int               $recursion = false,
        bool                   $dotDirectories = false,
        bool                   $dotFiles = false,
        bool                   $asString = false,
    ) : array {
        $scanner = new self( $directory, $extension, $recursion, $dotDirectories, $dotFiles );

        $scanner->scanDirectories( $asString ? [$scanner, 'returnFilePath'] : [$scanner, 'returnFileInfo'] );

        return $scanner->files;
    }

    /**
     * @param string                    $directory
     * @param null|bool|string|string[] $extension
     * @param bool|int                  $recursion
     * @param bool                      $dotDirectories
     * @param bool                      $dotFiles
     *
     * @return object{timestamp: int, extension: string, path: string, fileInfo: FileInfo}
     */
    public static function lastModified(
        string                 $directory,
        null|bool|string|array $extension = null,
        bool|int               $recursion = false,
        bool                   $dotDirectories = false,
        bool                   $dotFiles = false,
    ) : object {
        $scanner = new self( $directory, $extension, $recursion, $dotDirectories, $dotFiles );

        $scanner->scanDirectories( [$scanner, 'returnLastModified'] );

        return (object) [
            'timestamp' => $scanner->lastModified ?? 0,
            'extension' => $scanner->files[$scanner->lastModified]->getExtension(),
            'path'      => $scanner->files[$scanner->lastModified]->getRealPath(),
            'fileInfo'  => $scanner->files[$scanner->lastModified],
        ];
    }

    /**
     * @param string   $directory
     * @param bool|int $recursion
     *
     * @return Iterator<SplFileInfo>
     */
    public static function getIterator( string $directory, bool|int $recursion = false ) : Iterator
    {
        $iterator = new RecursiveDirectoryIterator( $directory );

        if ( $recursion ) {
            $iterator = new RecursiveIteratorIterator( $iterator );
            if ( \is_int( $recursion ) ) {
                $iterator->setMaxDepth( $recursion );
            }
        }

        return $iterator;
    }

    protected function scanDirectories( callable $action ) : void
    {
        foreach ( self::getIterator( $this->directory, $this->recursion ) as $fileInfo ) {
            // Prevent backtracking
            if ( $fileInfo->getBasename() === '..' ) {
                continue;
            }

            $item = new FileInfo( $fileInfo );

            // Skip unless $dotDirectories === true
            if ( $item->isDotDirectory() && ! $this->dotDirectories ) {
                continue;
            }

            // Skip unless $dotFiles === true
            if ( $item->isDotFile() && ! $this->dotFiles ) {
                continue;
            }

            // Directories only
            if ( $this->extension === false && ! $item->isDir() ) {
                continue;
            }

            // Files only
            if ( $this->extension === true && ! $item->isFile() ) {
                continue;
            }

            // Match against required .ext
            if ( $this->matchExtension( $item ) ) {
                continue;
            }

            $action( $item );
        }
    }

    protected function returnLastModified( FileInfo $fileInfo ) : void
    {
        if ( $fileInfo->getMTime() > $this->lastModified ) {
            $this->lastModified                 = $fileInfo->getMTime();
            $this->files[$fileInfo->getMTime()] = $fileInfo;
        }
    }

    protected function returnFileInfo( FileInfo $fileInfo ) : void
    {
        $this->files[] = $fileInfo;
    }

    protected function returnFilePath( FileInfo $fileInfo ) : void
    {
        $this->files[] = (string) $fileInfo;
    }

    protected function matchExtension( SplFileInfo $fileInfo ) : bool
    {
        return \is_array( $this->extension ) && ! \in_array( $fileInfo->getExtension(), $this->extension, true );
    }

    /**
     * @param null|bool|string[] $extension
     *
     * @return void
     */
    private function setExtension( null|bool|string|array $extension ) : void
    {
        if ( \is_string( $extension ) ) {
            $extension = \explode( ',', $extension );
        }

        if ( \is_array( $extension ) ) {
            foreach ( $extension as $i => $ext ) {
                $extension[$i] = \strtolower( \trim( $ext, " \t\n\r\0\x0B,." ) );
            }
        }

        $this->extension ??= $extension;
    }
}
