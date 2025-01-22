<?php

declare(strict_types=1);

namespace Support;

use Symfony\Component\Finder\Finder;
use Countable;
use SplFileInfo;
use Stringable;
use ReflectionClass;
use ReflectionAttribute;
use LogicException;
use InvalidArgumentException;

final class ClassFinder implements Countable
{
    /** @var array<string, class-string> `[basename => className]` */
    private array $matchAttributes = [];

    private ?bool $requiresAllAttributes = null;

    /** @var array<string, int> `[hash => count]` */
    protected array $lock = [];

    /** @var array<class-string|string, string> `[className => basename]` */
    protected array $found = [];

    public function __construct(
        public bool $validateClassExists = true,
        public bool $throwOnError = true,
    ) {}

    /**
     * Can be run repeatedly with different paramters.
     *
     * @param string|string[]|Stringable|Stringable[] $directories
     * @param string                                  ...$relativeDirectories relative to provided directories
     *
     * @return $this
     */
    public function scan(
        string|Stringable|array $directories,
        string               ...$relativeDirectories,
    ) : self {
        $directories         = $this::normalize( $directories );
        $relativeDirectories = $this::normalize( $relativeDirectories );

        $hash = \hash( algo : 'xxh3', data : \implode( ':', $directories + $relativeDirectories ) );

        // Prevent duplicate scans
        if ( \array_key_exists( $hash, $this->lock ) ) {
            return $this;
        }

        $find = new Finder();
        $find->files()
            ->name( '*.php' )
            ->in( $directories )
            ->exclude( $relativeDirectories );

        foreach ( $find as $file ) {
            $this->parseDiscoveredFile( $file );
        }

        $this->lock[$hash] = $find->count();

        return $this;
    }

    private function parseDiscoveredFile( SplFileInfo $path ) : void
    {
        $filePath = $path->getRealPath();

        $stream = \fopen( $filePath, 'r' );

        if ( false === $stream ) {
            $message = __CLASS__.' is unable to open file : '.$filePath;
            throw new InvalidArgumentException( $message );
        }

        $basename      = null;
        $namespace     = null;
        $attributes    = [];
        $hasAttributes = false;

        while ( false !== ( $line = \fgets( $stream ) ) ) {
            $line = \trim(
                (string) \preg_replace(
                    [
                        '/\s+/',    // Normalize repeated whitespace,
                        '/#\[\h*/', // Normalize #[ Attribute lines
                    ],
                    [' ', '#['],
                    $line,
                ),
            );

            if ( \str_starts_with( $line, 'namespace ' ) ) {
                $namespace ??= \trim( \substr( $line, \strlen( 'namespace' ) ), " \n\r\t\v\0;" );
            }

            if ( ! $hasAttributes && \str_starts_with( $line, '#[' ) ) {
                $hasAttributes = true;
            }

            foreach ( $this->matchAttributes as $attribute => $_ ) {
                if ( \str_starts_with( $line, "#[{$attribute}" ) ) {
                    $attributes[$attribute] = true;
                }
                else {
                    $attributes[$attribute] ??= false;
                }
            }

            if ( $this->lineContainsDefinition( $line, $basename ) ) {
                break;
            }
        }

        \fclose( $stream );

        if ( ! $basename || ! $hasAttributes ) {
            return;
        }

        if ( $this->matchAttributes ) {
            $attributes = \array_filter( $attributes );
            if ( $this->requiresAllAttributes
                 && \array_keys( $this->matchAttributes ) !== \array_keys( $attributes )
            ) {
                return;
            }

            if ( ! $attributes ) {
                return;
            }
        }

        $className = $namespace.'\\'.$basename;

        if ( $this->validateClassExists && ! \class_exists( $className ) ) {
            return;
        }

        $this->found[$className] = $basename;
    }

    private function lineContainsDefinition(
        string  $line,
        ?string & $className,
    ) : bool {
        if ( ! \str_contains( $line, 'class ' ) ) {
            return false;
        }

        foreach ( [
            'final class ',
            'final readonly class ',
            'abstract class ',
            'abstract readonly class ',
            'readonly class ',
            'class ',
        ] as $type ) {
            if ( \str_starts_with( $line, $type ) ) {
                $classString = \substr( $line, \strlen( $type ) );

                // Update &$className by reference
                $className = \strstr( $classString, ' ', true ) ?: $classString;

                return true;
            }
        }

        return false;
    }

    /**
     * @template T of object
     *
     * @param ?class-string<T> $attribute
     *
     * @return ($attribute is class-string ? array<class-string, ReflectionAttribute<T>> : array<class-string, array<class-string, ReflectionAttribute<T>>>)
     */
    public function getAttributes( ?string $attribute = null ) : array
    {
        if ( ! $this->matchAttributes || ! $this->hasResults() ) {
            return [];
        }

        $getAttributes = (array) ( $attribute ?? $this->matchAttributes );

        $single = null !== $attribute;
        $array  = [];

        foreach ( $this->found as $className => $basename ) {
            $attributes = [];
            // try {
            \assert( \class_exists( $className ) );
            $reflection = new ReflectionClass( $className );

            foreach ( $getAttributes as $attributeClass ) {
                $reflectedAttribute = $reflection->getAttributes(
                    name  : $attributeClass,
                    flags : ReflectionAttribute::IS_INSTANCEOF,
                );
                if ( empty( $reflectedAttribute ) ) {
                    continue;
                }
                if ( \count( $reflectedAttribute ) !== 1 ) {
                    throw new LogicException(
                        'Attribute '.$attributeClass.' does not have '.$reflectedAttribute[0],
                    );
                }
                $attributes[$attributeClass] = \end( $reflectedAttribute );
            }
            // }
            // catch ( ReflectionException $e ) {
            //     if ( $this->throwOnError ) {
            //         throw new RuntimeException( $e->getMessage() );
            //     }
            // }

            if ( ! $attributes ) {
                continue;
            }

            /** @var array<class-string, ReflectionAttribute<T>> $attributes */
            if ( $single ) {
                $array[$className] = \end( $attributes ) ?? throw new InvalidArgumentException();
                /** @var array<class-string, ReflectionAttribute<T>> $array */
            }
            else {
                $array[$className] = $attributes;
                /** @var array<class-string,array<class-string, ReflectionAttribute<T>>> $array */
            }
        }

        return $array;
    }

    /**
     * @param class-string[] $attribute
     * @param bool           $requiresAll
     *
     * @return self
     */
    public function withAttribute( string|array $attribute, bool $requiresAll = false ) : self
    {
        $this->requiresAllAttributes ??= $requiresAll;

        foreach ( (array) $attribute as $className ) {
            if ( \class_exists( $className ) ) {
                $this->matchAttributes[classBasename( $className )] = $className;
            }
            else {
                throw new InvalidArgumentException( 'Attribute Class '.$className.' does not exist' );
            }
        }
        return $this;
    }

    /**
     * @param class-string $className
     *
     * @return bool
     */
    public function has( string $className ) : bool
    {
        return \array_key_exists( $className, $this->found );
    }

    public function count() : int
    {
        return \count( $this->found );
    }

    /**
     * @return class-string[]
     */
    public function getFoundClasses() : array
    {
        /** @var class-string[] */
        return \array_keys( $this->found );
    }

    /**
     * @return array<class-string|string, string>
     */
    public function getResults() : array
    {
        return $this->found;
    }

    public function hasResults() : bool
    {
        return ! empty( $this->found );
    }

    /**
     * @param string|string[]|Stringable|Stringable[] $directories
     *
     * @return string[]
     */
    private static function normalize( string|Stringable|array $directories ) : array
    {
        $directories = (array) $directories;

        foreach ( $directories as $i => $path ) {
            $normalized      = (string) \str_replace( ['\\', '/'], DIRECTORY_SEPARATOR, (string) $path );
            $directoryPath   = \rtrim( $normalized, DIRECTORY_SEPARATOR );
            $directories[$i] = $directoryPath;
        }
        return $directories;
    }
}
