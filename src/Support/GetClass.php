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

    public readonly string $class_string;

    public readonly bool $exists;

    public readonly Path $path;

    protected array $types = [];

    protected array $interfaces;

    protected array $traits;

    protected array $parents;

    private function __construct(
        Path|string $source,
        bool        $validate = null,
    ) {
        if ( $source instanceof Path ) {
            $this->path = $source;
            $this->parseFile();
        }

        // find path to passed Namespace/Class

        $this->namespace = \implode( '\\', $this->namespaces ) ?: null;

        $this->class_string = \implode( '\\', [...$this->namespaces, $this->class] );

        $this->exists = \class_exists( $this->class_string );
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

            if ( \str_contains( $line, 'class ' ) && Str::startsWith( $line, $this::TYPES ) ) {
                $this->class = $this->parseClassName( $line );

                break;
            }
        }
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

    public function implements( string $interface ) : bool
    {
        return \in_array( $interface, $this->getInterfaces(), true );
    }

    public function extends( string $class ) : bool
    {
        return \in_array( $class, $this->getParents(), true );
    }

    public function uses( string $trait ) : bool
    {
        return \in_array( $trait, $this->getTraits(), true );
    }

    public function getInterfaces() : array
    {
        return $this->interfaces ??= \class_implements( $this->class_string, false ) ?: [];
    }

    public function getTraits() : array
    {
        return $this->traits ??= get_traits( $this->class_string ) ?: [];
    }

    public function getParents() : array
    {
        return $this->parents ??= \class_parents( $this->class_string ) ?: [];
    }

    public static function fromString( string $string ) : self
    {
        if ( \str_ends_with( $string, '.php' ) ) {
            return self::fromFile( $string );
        }
        return new self( $string );
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

        return new GetClass( $path );
    }
}
