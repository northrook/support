<?php

declare(strict_types=1);

namespace Support;

use InvalidArgumentException;
use Northrook\Filesystem\Path;

final class GetClass
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

    /** @var array<int, string> */
    protected array $namespaces = [];

    public readonly ?string $namespace;

    public readonly string $class;

    /** @var class-string|string */
    public readonly string $class_string;

    public readonly bool $exists;

    public readonly Path $path;

    /** @var array<int, string> */
    protected array $types = [];

    /** @var class-string[] */
    protected array $interfaces;

    /** @var class-string[] */
    protected array $traits;

    /** @var class-string[] */
    protected array $parents;

    private function __construct(
        Path|string $source,
        bool        $validate = false,
    ) {
        if ( $source instanceof Path ) {
            $this->path = $source;
            $this->parseFile();
        }

        // find path to passed Namespace/Class

        $this->namespace = \implode( '\\', $this->namespaces ) ?: null;

        $this->class_string = \implode( '\\', [...$this->namespaces, $this->class] );

        $this->exists = \class_exists( $this->class_string );

        if ( $validate && ! $this->exists ) {
            throw new InvalidArgumentException( "The class {$this->class_string} does not exists." );
        }
    }

    /**
     * Check if the `class` implements a given `interface`.
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
     * Check if the `class` implements a given `class`.
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
     * Check if the `class` implements a given `trait`.
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
        return $this->interfaces ??= \class_implements( $this->class_string, false ) ?: [];
    }

    /**
     * @return class-string[]
     */
    public function getTraits() : array
    {
        return $this->traits ??= get_traits( $this->class_string ) ?: [];
    }

    /**
     * @return class-string[]
     */
    public function getParents() : array
    {
        return $this->parents ??= \class_parents( $this->class_string ) ?: [];
    }

    public static function fromString( string $string, bool $strict = false ) : self
    {
        if ( \str_ends_with( $string, '.php' ) ) {
            return self::fromFile( $string, $strict );
        }
        return new self( $string, $strict );
    }

    public static function fromFile( string $path, bool $strict = false ) : GetClass
    {
        $path = new Path( $path );

        if ( ! $path->exists() ) {
            throw new InvalidArgumentException( "The provided path '{$path}' does not exist." );
        }

        if ( ! $path->isReadable ) {
            throw new InvalidArgumentException( "The provided path '{$path}' is not readable." );
        }

        if ( $strict && 'php' !== $path->extension ) {
            throw new InvalidArgumentException( "The provided path '{$path}' must be to a PHP file." );
        }

        return new GetClass( $path, $strict );
    }

    private function parseFile() : void
    {
        $filePath = (string) $this->path;

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
                $this->class = $this->parseClassName( $line );

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

    private function parseClassName( string $line ) : string
    {
        foreach ( $this::TYPES as $type ) {
            if ( \str_starts_with( $line, $type ) ) {
                $this->types[] = $type;
                return $this->parseClassName( \substr( $line, \strlen( $type ) + 1 ) );
            }
        }

        $line = \trim( $line );

        return \explode( ' ', $line )[0];
    }
}
