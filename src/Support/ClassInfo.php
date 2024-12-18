<?php

declare(strict_types=1);

namespace Support;

use BadMethodCallException;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

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

    protected readonly ReflectionClass $reflection;

    /** @var array<int, string> */
    protected array $namespaces = [];

    /** @var ?string [null] on Global namespace */
    public readonly ?string $namespace;

    public readonly string $className;

    /** @var class-string|string */
    public readonly string $class;

    public readonly bool $exists;

    public readonly ?FileInfo $fileInfo;

    /** @var array<int, string> */
    protected array $types = [];

    /** @var class-string[] */
    protected array $interfaces;

    /** @var class-string[] */
    protected array $traits;

    /** @var class-string[] */
    protected array $parents;

    /**
     * @param class-string|FileInfo|string $source
     * @param bool                         $validate
     */
    public function __construct(
        FileInfo|string $source,
        bool            $validate = false,
    ) {
        if ( $this->asSourceFilePath( $source ) ) {
            $this->fileInfo = $source;
            $this->parseFile();
            $this->namespace = \implode( '\\', $this->namespaces ) ?: null;
            $this->class     = \implode( '\\', [...$this->namespaces, $this->className] );
        }
        else {
            $this->class    = $source;
            $filePath       = $this->reflect()->getFileName();
            $this->fileInfo = $filePath ? new FileInfo( $filePath ) : null;
            $source         = \explode( '\\', $source );

            $this->className  = \array_pop( $source ) ?: throw new InvalidArgumentException();
            $this->namespaces = $source;
            $this->namespace  = \implode( '\\', $this->namespaces ) ?: null;
        }

        // find path to passed Namespace/Class

        $this->exists = \class_exists( $this->class );

        if ( $validate && ! $this->exists ) {
            throw new InvalidArgumentException( "The class {$this->class} cannot be loaded." );
        }
    }

    public function reflect() : ReflectionClass
    {
        try {
            return $this->reflection ??= new ReflectionClass( $this->class );
        }
        catch ( ReflectionException $exception ) {
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
     * @param FileInfo|string $source
     *
     * @phpstan-assert-if-false string  $source
     * @phpstan-assert-if-true FileInfo $source
     * @return bool
     */
    private function asSourceFilePath( FileInfo|string &$source ) : bool
    {
        if ( \is_string( $source ) && \str_ends_with( $source, '.php' ) ) {
            $source = new FileInfo( $source );
        }

        if ( $source instanceof FileInfo ) {
            if ( ! $source->exists() ) {
                throw new InvalidArgumentException( "The provided path '{$source}' does not exist." );
            }

            if ( ! $source->isReadable() ) {
                throw new InvalidArgumentException( "The provided path '{$source}' is not readable." );
            }

            return true;
        }

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

        if ( false === $stream ) {
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
