<?php

declare(strict_types=1);

namespace Support;

use JetBrains\PhpStorm\Deprecated;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem as Symfony;
use Symfony\Component\Filesystem\Exception\IOException;
use Throwable;
use LogicException;

#[Deprecated]
class Filesystem
{
    /**
     * Mimetypes for simple .extension lookup.
     *
     * @see      https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types
     */
    private const array MIME_TYPES = [
        // Text and XML
        'txt'  => 'text/plain',
        'htm'  => 'text/html',
        'html' => 'text/html',
        'php'  => 'text/html',
        'css'  => 'text/css',
        'js'   => 'text/javascript',

        // Documents
        'rtf' => 'application/rtf',
        'doc' => 'application/msword',
        'pdf' => 'application/pdf',
        'eps' => 'application/postscript',

        // Data sources
        'csv'    => 'text/csv',
        'json'   => 'application/json',
        'jsonld' => 'application/ld+json',
        'xls'    => 'application/vnd.ms-excel',
        'xml'    => 'application/xml',

        // Images and vector graphics
        'apng' => 'image/png',
        'png'  => 'image/png',
        'jpe'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg'  => 'image/jpeg',
        'gif'  => 'image/gif',
        'bmp'  => 'image/bmp',
        'ico'  => 'image/vnd.microsoft.icon',
        'tiff' => 'image/tiff',
        'tif'  => 'image/tiff',
        'svg'  => 'image/svg+xml',
        'svgz' => 'image/svg+xml',
        'webp' => 'image/webp',
        'webm' => 'video/webm',

        // archives
        '7z'  => 'application/x-7z-compressed',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'exe' => 'application/x-msdownload',
        'msi' => 'application/x-msdownload',
        'cab' => 'application/vnd.ms-cab-compressed',
        'tar' => 'application/x-tar',

        // audio/video
        'mp3' => 'audio/mpeg',
        'qt'  => 'video/quicktime',
        'mov' => 'video/quicktime',

        // Fonts
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'eot'   => 'application/vnd.ms-fontobject',
    ];

    private static ?Filesystem $instance = null;

    private readonly Symfony\Filesystem $filesystem;

    #[Deprecated]
    final public function __construct(
        ?Symfony\Filesystem               $filesystem = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
        dump( __METHOD__, ...\debug_backtrace() );
        if ( $this::$instance ) {
            return;
        }
        $this->filesystem = $filesystem ?? new Symfony\Filesystem();
        $this::$instance  = $this;
    }

    /**
     * Checks the existence of files or directories.
     *
     * @param string ...$path The files to check
     *
     * @return bool
     */
    final public static function exists( string ...$path ) : bool
    {
        try {
            return self::get()->filesystem->exists( ...$path );
        }
        catch ( IOException $exception ) {
            self::handleError( $exception );
        }
        return false;
    }

    /**
     * Checks the provided paths are directories.
     *
     * @param string ...$path The paths to check
     *
     * @return bool
     */
    final public static function isDir( string ...$path ) : bool
    {
        foreach ( $path as $file ) {
            if ( ! \is_dir( $file ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks the provided paths are files.
     *
     * @param string ...$path The paths to check
     *
     * @return bool
     */
    final public static function isFile( string ...$path ) : bool
    {
        foreach ( $path as $file ) {
            if ( ! \is_file( $file ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks the provided paths can be read.
     *
     * @param string ...$path The files to check
     *
     * @return bool
     */
    final public static function isReadable( string ...$path ) : bool
    {
        foreach ( $path as $file ) {
            if ( ! \is_readable( $file ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if files or directories can be written to.
     *
     * @param string ...$path The files to check
     *
     * @return bool
     */
    final public static function isWritable( string ...$path ) : bool
    {
        foreach ( $path as $file ) {
            if ( ! \is_writable( $file ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks the provided paths are URL strings.
     *
     *  - Does not validate the response.
     *
     * @param string ...$path The paths to check
     *
     * @return bool
     */
    final public static function isFilePath( string ...$path ) : bool
    {
        foreach ( $path as $file ) {
            if ( isUrl( $file ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sets access and modification time of file.
     *
     * @param string|string[] $files The files to touch
     * @param ?int            $time  The touch time as a Unix timestamp, if not supplied the current system time is used
     * @param ?int            $atime The access time as a Unix timestamp, if not supplied the current system time is used
     *
     * @return bool
     */
    final public static function touch( string|array $files, ?int $time = null, ?int $atime = null ) : bool
    {
        try {
            self::get()->filesystem->touch( $files, $time, $atime );
            return true;
        }
        catch ( IOException $exception ) {
            self::handleError( $exception );
        }
        return false;
    }

    /**
     * Reads the contents of a file.
     *
     * - {@see IOException} will be caught and logged as an error, returning `null`
     *
     * @param string $path The path to the file
     *
     * @return ?string Returns the contents of the file, or null if an {@see IOException} was thrown
     */
    final public static function read( string $path ) : ?string
    {
        try {
            return self::get()->filesystem->readFile( $path );
        }
        catch ( IOException $exception ) {
            self::handleError( $exception );
            self::handleError( $exception );
            self::handleError( $exception );
            self::handleError( $exception );
        }
        return null;
    }

    /**
     * Atomically dumps content into a file.
     *
     * - {@see IOException} will be caught and logged as an error, returning false
     *
     * @param string          $path    The path to the file
     * @param resource|string $content The data to write into the file
     *
     * @return bool True if the file was written to, false if it already existed or an error occurred
     */
    final public static function save( string $path, mixed $content ) : bool
    {
        try {
            self::get()->filesystem->dumpFile( $path, $content );
            return true;
        }
        catch ( IOException $exception ) {
            self::handleError( $exception );
        }

        return false;
    }

    /**
     * Copies {@see $originFile} to {@see $targetFile}.
     *
     * - If the target file is automatically overwritten when this file is newer.
     * - If the target is newer, $overwriteNewerFiles decides whether to overwrite.
     * - {@see IOException}s will be caught and logged as an error, returning false
     *
     * @param string $originFile
     * @param string $targetFile
     * @param bool   $overwriteNewerFiles
     *
     * @return bool True if the file was written to, false if it already existed or an error occurred
     */
    final public static function copy(
        string $originFile,
        string $targetFile,
        bool   $overwriteNewerFiles = false,
    ) : bool {
        try {
            self::get()->filesystem->copy( $originFile, $targetFile, $overwriteNewerFiles );
            return true;
        }
        catch ( IOException $exception ) {
            self::handleError( $exception );
        }

        return false;
    }

    /**
     * Renames a file or a directory.
     *
     * @param string $origin
     * @param string $target
     * @param bool   $overwrite
     *
     * @return bool
     */
    final public static function rename( string $origin, string $target, bool $overwrite = false ) : bool
    {
        try {
            self::get()->filesystem->rename( $origin, $target, $overwrite );
            return true;
        }
        catch ( IOException $exception ) {
            self::handleError( $exception );
        }
        return false;
    }

    /**
     * Creates a directory recursively.
     *
     * @param string|string[] $dirs
     * @param int             $mode
     * @param bool            $returnPath
     *
     * @return ($returnPath is true ? false|string|string[] : bool)
     */
    final public static function mkdir(
        string|array $dirs,
        int          $mode = 0777,
        bool         $returnPath = false,
    ) : bool|string|array {
        try {
            self::get()->filesystem->mkdir( $dirs, $mode );
            return $returnPath ? $dirs : true;
        }
        catch ( IOException $exception ) {
            self::handleError( $exception );
        }
        return false;
    }

    /**
     * Removes files or directories.
     *
     * @param string|string[] $files
     *
     * @return bool
     */
    final public static function remove( string|array $files ) : bool
    {
        try {
            self::get()->filesystem->remove( $files );
            return true;
        }
        catch ( IOException $exception ) {
            self::handleError( $exception );
        }
        return false;
    }

    /**
     * @param string $path
     * @param bool   $format
     *
     * @return null|int|string
     */
    final public static function size( string $path, bool $format = false ) : null|int|string
    {
        try {
            $size = \filesize( $path );

            if ( ! $size ) {
                throw new IOException(
                    message : "Could not determine the size for path '{$path}'.",
                    code    : 500,
                    path    : $path,
                );
            }

            return $format ? Num::byteSize( $size ) : $size;
        }
        catch ( IOException $exception ) {
            self::handleError( $exception );
        }

        return null;
    }

    /**
     * @param string $path
     *
     * @return null|string
     */
    final public static function getMimeType( string $path ) : ?string
    {
        try {
            return Filesystem::MIME_TYPES[\pathinfo( $path, PATHINFO_EXTENSION )];
        }
        catch ( Throwable $exception ) {
            self::handleError( $exception, "Unable to resolve mime type '{$exception->getMessage()}'", $path );
        }
        return null;
    }

    /**
     * Ensure the static instance is cleared in case a Persistent Server Model is used.
     */
    final public function __destruct()
    {
        $this->logger?->debug( 'Cleared static {class} instance.', ['class' => self::class] );
        $this::$instance = null;
    }

    /**
     * @internal
     *
     * @param Throwable   $exception
     * @param null|string $message
     * @param null|string $path
     *
     * @return void
     */
    private static function handleError( Throwable $exception, ?string $message = null, ?string $path = null ) : void
    {
        if ( self::get()->logger ) {
            self::get()->logger->error( $exception->getMessage() );
        }
        else {
            throw new IOException(
                message  : $message ?? $exception->getMessage(),
                code     : 500,
                previous : $exception,
                path     : $path,
            );
        }
    }

    /**
     * @internal
     * @return self
     */
    private static function get() : self
    {
        return Filesystem::$instance ??= new self( new Symfony\Filesystem() );
    }

    final protected function __clone()
    {
        throw new LogicException( $this::class.' is static, and should not be cloned.' );
    }
}
