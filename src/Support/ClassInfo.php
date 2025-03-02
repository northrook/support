<?php

declare(strict_types=1);

namespace Support;

use BadMethodCallException;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use SplFileInfo;
use Throwable;

/**
 * @template T of object
 * @template-covariant T as class-string<T>
 */
final class ClassInfo
{
    private const array TYPES = [
        'final',
        'abstract',
        'readonly',
        'class',
        'enum',
        'trait',
        'interface',
    ];

    /** @var ReflectionClass<T> */
    protected readonly ReflectionClass $reflection;

    /** @var array<int, string> */
    protected array $namespaces = [];

    /** @var ?string [null] on Global namespace */
    public readonly ?string $namespace;

    public readonly string $className;

    /** @var class-string<T> */
    public readonly string $class;

    public readonly bool $exists;

    public readonly ?SplFileInfo $fileInfo;

    /** @var array<int, string> */
    protected array $types = [];

    /** @var class-string[] */
    protected array $interfaces;

    /** @var class-string[] */
    protected array $traits;

    /** @var class-string[] */
    protected array $parents;

    /**
     * @param class-string<T>|FileInfo|string $source
     * @param bool                            $validate
     * @param bool                            $discover
     */
    public function __construct(
        string|object $source,
        bool          $validate = false,
        bool          $discover = false,
    ) {
        if ( $this->asSourceFilePath( $source ) ) {
            $this->fileInfo = $source;
            $this->parseFile();
            $this->namespace = \implode( '\\', $this->namespaces ) ?: null;
            /** @var class-string<T> $source */
            $source      = \implode( '\\', [...$this->namespaces, $this->className] );
            $this->class = $source;
        }
        else {
            /** @var class-string<T> $source */
            $this->class    = $source;
            $filePath       = $this->reflect()->getFileName();
            $this->fileInfo = $filePath ? new SplFileInfo( $filePath ) : null;
            /** @var string[] $source */
            $source = \explode( '\\', $source );

            $this->className  = \array_pop( $source ) ?: throw new InvalidArgumentException();
            $this->namespaces = $source;
            $this->namespace  = \implode( '\\', $this->namespaces ) ?: null;
        }

        // find path to passed Namespace/Class

        $this->exists = \class_exists( $this->class );

        if ( $validate && ! $this->exists ) {
            throw new InvalidArgumentException( "The class {$this->class} cannot be loaded." );
        }

        if ( $discover ) {
            $this->getInterfaces();
            $this->getTraits();
            $this->getParents();
        }
    }

    /**
     * @return ReflectionClass<T>
     */
    public function reflect() : ReflectionClass
    {
        try {
            return $this->reflection ??= new ReflectionClass( $this->class );
        }
        // @phpstan-ignore-next-line
        catch ( Throwable $exception ) {
            throw new BadMethodCallException( $exception->getMessage(), 500, $exception );
        }
    }

    public function hasMethod( string ...$name ) : bool
    {
        foreach ( $name as $method ) {
            if ( ! $this->reflect()->hasMethod( $method ) ) {
                return false;
            }
        }
        return true;
    }

    public function getMethod( string $name ) : ReflectionMethod
    {
        try {
            return $this->reflect()->getMethod( $name );
        }
        catch ( ReflectionException $exception ) {
            throw new BadMethodCallException( $exception->getMessage(), 500, $exception );
        }
    }

    /**
     * # Get the class name of a provided class, or the calling class.
     *
     * - Will use the `debug_backtrace()` to get the calling class if no `$class` is provided.
     *
     * ```
     * $class = new \Northrook\Core\Env();
     * classBasename( $class );
     * // => 'Env'
     * ```
     *
     * @param class-string|object|string $class
     * @param ?callable-string           $filter {@see \strtolower} by default
     *
     * @return string
     */
    public static function basename( string|object $class, ?string $filter = 'strtolower' ) : string
    {
        $className  = \is_object( $class ) ? $class::class : $class;
        $namespaced = \explode( '\\', $className );
        $basename   = \end( $namespaced );

        if ( \is_callable( $filter ) ) {
            return $filter( $basename );
        }

        return $basename;
    }

    /**
     * @param SplFileInfo|string $source
     *
     * @phpstan-assert-if-false string     $source
     * @phpstan-assert-if-true SplFileInfo $source
     * @return bool
     */
    private function asSourceFilePath( object|string &$source ) : bool
    {
        if ( \is_string( $source ) && \str_ends_with( $source, '.php' ) ) {
            $source = new SplFileInfo( $source );
        }

        if ( $source instanceof SplFileInfo ) {
            if ( ! $source->isFile() ) {
                throw new InvalidArgumentException( "The provided path '{$source}' does not exist." );
            }

            if ( ! $source->isReadable() ) {
                throw new InvalidArgumentException( "The provided path '{$source}' is not readable." );
            }

            return true;
        }

        \assert( \class_exists( $source, false ) );

        $source = (string) class_name( $source );

        return false;
    }

    /**
     * Check if the {@see self::class} implements a given `$interface`.
     *
     * @param string $interface
     *
     * @return bool
     */
    public function implements( string $interface ) : bool
    {
        return \in_array( $interface, $this->getInterfaces(), true );
    }

    /**
     * Check if the {@see self::class} implements a given `class`.
     *
     * @param string $class
     *
     * @return bool
     */
    public function extends( string $class ) : bool
    {
        return \in_array( $class, $this->getParents(), true );
    }

    /**
     * Check if the {@see self::class} implements a given `$trait`.
     *
     * @param string $trait
     *
     * @return bool
     */
    public function uses( string $trait ) : bool
    {
        return \in_array( $trait, $this->getTraits(), true );
    }

    /**
     * @return class-string[]
     */
    public function getInterfaces() : array
    {
        return $this->interfaces ??= \class_implements( $this->class ) ?: [];
    }

    /**
     * @return class-string[]
     */
    public function getTraits() : array
    {
        return $this->traits ??= get_traits( $this->class ) ?: [];
    }

    /**
     * @return class-string[]
     */
    public function getParents() : array
    {
        return $this->parents ??= \class_parents( $this->class ) ?: [];
    }

    private function parseFile() : void
    {
        $filePath = (string) $this->fileInfo;

        $stream = \fopen( $filePath, 'r' );

        if ( $stream === false ) {
            throw new InvalidArgumentException( 'Unable to open file: '.$filePath );
        }

        while ( false !== ( $line = \fgets( $stream ) ) ) {
            $line = \trim( (string) \preg_replace( '/\s+/', ' ', $line ) );

            if ( \str_starts_with( $line, 'namespace ' ) ) {
                $namespace        = \substr( $line, \strlen( 'namespace' ) );
                $this->namespaces = \explode( '\\', \trim( $namespace, " \n\r\t\v\0;" ) );
            }

            if ( $this->lineContainsDefinition( $line ) ) {
                $this->className = $this->setClassName( $line );

                break;
            }
        }
        \fclose( $stream );
    }

    private function lineContainsDefinition( string $line ) : bool
    {
        if ( ! \str_contains( $line, 'class ' ) ) {
            return false;
        }

        foreach ( $this::TYPES as $type ) {
            if ( \str_starts_with( $line, $type ) ) {
                return true;
            }
        }

        return false;
    }

    private function setClassName( string $line ) : string
    {
        foreach ( $this::TYPES as $type ) {
            if ( \str_starts_with( $line, $type ) ) {
                $this->types[] = $type;
                return $this->setClassName( \substr( $line, \strlen( $type ) + 1 ) );
            }
        }

        $line = \trim( $line );

        return \explode( ' ', $line )[0];
    }
}
