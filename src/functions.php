<?php

declare(strict_types=1);

namespace {

    if ( ! defined( 'ENCODING' ) ) {
        define( 'ENCODING', 'UTF-8' );
    }
}

namespace Support {

    use InvalidArgumentException;
    use JetBrains\PhpStorm\{Deprecated};
    use function Cache\memoize;

    // <editor-fold desc="Constants">

    const URL_SAFE_CHARACTERS_UNICODE   = "\w.,_~:;@!$&*?#=%()+\-\[\]\'\/";
    const URL_SAFE_CHARACTERS           = "A-Za-z0-9.,_~:;@!$&*?#=%()+\-\[\]\'\/";
    const ENCODE_ESCAPE_JSON            = JSON_UNESCAPED_UNICODE       | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
    const ENCODE_PARTIAL_UNESCAPED_JSON = JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE;
    const FILTER_STRING_COMMENTS        = [
        '{* '   => '<!-- ', // Latte
        ' *}'   => ' -->',
        '{# '   => '<!-- ', // Twig
        ' #}'   => ' -->',
        '{{-- ' => '<!-- ', // Blade
        ' --}}' => ' -->',
    ];

    // </editor-fold>

    // <editor-fold desc="System">

    /**
     * @param \DateTimeInterface|string $when
     * @param null|\DateTimeZone|string $timezone [UTC]
     *
     * @return \DateTimeImmutable
     */
    #[Deprecated( 'Use Support\timestamp' )]
    function getTimestamp(
        string|\DateTimeInterface $when = 'now',
        string|\DateTimeZone|null $timezone = null,
    ) : \DateTimeImmutable {
        $fromDateTime = $when instanceof \DateTimeInterface;
        $datetime     = (string) ( $fromDateTime ? $when->getTimestamp() : $when );

        $timezone = match ( true ) {
            \is_null( $timezone )   => $fromDateTime ? $when->getTimezone() : \timezone_open( 'UTC' ),
            \is_string( $timezone ) => \timezone_open( $timezone ),
            default                 => $timezone,
        };

        try {
            return new \DateTimeImmutable( $datetime, $timezone ?: null );
        }
        catch ( \Exception $exception ) {
            $message = 'Unable to create a new DateTimeImmutable object: '.$exception->getMessage();
            throw new InvalidArgumentException( $message, 500, $exception );
        }
    }

    /**
     * Retrieves the project root directory.
     *
     * - This function assumes the Composer directory is present in the project root.
     * - The return is cached using {@see \Cache\memoize()}.
     *
     * @param ?string $append
     *
     * @return string
     */
    #[Deprecated( 'Use Support\getProjectDirectory' )]
    function getProjectRootDirectory( ?string $append = null ) : string
    {
        return memoize(
            static function() use ( $append ) : string {
                // Split the current directory into an array of directory segments
                $segments = \explode( \DIRECTORY_SEPARATOR, __DIR__ );

                // Ensure the directory array has at least 5 segments and a valid vendor value
                if ( ( \count( $segments ) >= 5 && $segments[\count( $segments ) - 4] === 'vendor' ) ) {
                    // Remove the last 4 segments (vendor, package name, and Composer structure)
                    $rootSegments = \array_slice( $segments, 0, -4 );
                }
                // Look for a src value
                elseif ( \in_array( 'src', $segments, true ) ) {
                    $srcKey = (int) Arr::search( $segments, 'src' );

                    $rootSegments = \array_slice( $segments, 0, $srcKey );
                }
                else {
                    $message = __FUNCTION__.' was unable to determine a valid root. Current path: '.__DIR__;
                    throw new \BadFunctionCallException( $message );
                }

                // Normalize and return the project path
                return Normalize::path( [...$rootSegments, $append] );
            },
            __FUNCTION__,
        );
    }

    // </editor-fold>

    // <editor-fold desc="Path">

    /**
     * @param string                        $path
     * @param bool                          $throw
     * @param null|InvalidArgumentException $exception
     *
     * @return bool
     */
    function path_valid(
        string                   $path,
        bool                     $throw = false,
        InvalidArgumentException & $exception = null,
    ) : bool {
        // Ensure we are not receiving any previously set exceptions
        $exception = null;

        // Check if path exists and is readable
        $isReadable = \is_readable( $path );
        $exists     = \file_exists( $path ) && $isReadable;

        // Return early
        if ( $exists ) {
            return true;
        }

        // Determine path type
        $type = \is_dir( $path ) ? 'dir' : ( \is_file( $path ) ? 'file' : false );

        // Handle non-existent paths
        if ( ! $type ) {
            $exception = new InvalidArgumentException( "The '{$path}' does not exist." );
            if ( $throw ) {
                throw $exception;
            }
            return false;
        }

        $isWritable = \is_writable( $path );

        $error = ( ! $isWritable && ! $isReadable ) ? ' is not readable nor writable.' : null;
        $error ??= ( ! $isReadable ) ? ' not writable.' : null;
        $error ??= ( ! $isReadable ) ? ' not unreadable.' : null;
        $error ??= ' encountered a filesystem error. The cause could not be determined.';

        // Create exception message
        $exception = new InvalidArgumentException( "The path '{$path}' {$error}" );

        if ( $throw ) {
            throw $exception;
        }

        return false;
    }

    /**
     * @param string                        $path
     * @param bool                          $throw     [false]
     * @param null|InvalidArgumentException $exception
     *
     * @return bool
     */
    function path_readable(
        string                   $path,
        bool                     $throw = false,
        InvalidArgumentException & $exception = null,
    ) : bool {
        $exception = null;

        if ( ! \file_exists( $path ) ) {
            $exception = new InvalidArgumentException(
                'The file at "'.$path.'" does not exist.',
                500,
            );
            if ( $throw ) {
                throw $exception;
            }
        }

        if ( ! \is_readable( $path ) ) {
            $exception = new InvalidArgumentException(
                \sprintf( 'The "%s" "%s" is not readable.', \is_dir( $path ) ? 'directory' : 'file', $path ),
                500,
            );
            if ( $throw ) {
                throw $exception;
            }
        }

        return ! $exception;
    }

    /**
     * @param string                        $path
     * @param bool                          $throw     [false]
     * @param null|InvalidArgumentException $exception
     *
     * @return bool
     */
    function path_writable(
        string                   $path,
        bool                     $throw = false,
        InvalidArgumentException & $exception = null,
    ) : bool {
        $exception = null;

        if ( ! \file_exists( $path ) ) {
            $exception = new InvalidArgumentException(
                'The file at "'.$path.'" does not exist.',
                500,
            );
            if ( $throw ) {
                throw $exception;
            }
        }

        if ( ! \is_writable( $path ) ) {
            $exception = new InvalidArgumentException(
                \sprintf( 'The "%s" "%s" is not writable.', \is_dir( $path ) ? 'directory' : 'file', $path ),
                500,
            );
            if ( $throw ) {
                throw $exception;
            }
        }

        return ! $exception;
    }

    // </editor-fold>

    /**
     * This function tries very hard to return a string from any given $value.
     *
     * @param mixed  $value
     * @param string $separator
     * @param bool   $filter
     *
     * @return string
     */
    function toString( mixed $value, string $separator = '', bool $filter = true ) : string
    {
        if ( ( $value ) ) {
            return (string) $value;
        }

        if ( isIterable( $value ) ) {
            $array = \iterator_to_array( $value );

            return \implode( $separator, $filter ? Arr::filter( $array ) : $array );
        }

        if ( \is_object( $value ) ) {
            try {
                return \serialize( $value );
            }
            catch ( \Throwable ) {
                return $value::class;
            }
        }

        // @var scalar $value
        return (string) @\json_encode( $value );
    }

    /**
     * Get a boolean option from an array of options.
     *
     * ⚠️ Be careful if passing other nullable values, as they will be converted to booleans.
     *
     * - Pass an array of options, `get_defined_vars()` is recommended.
     * - All 'nullable' values will be converted to booleans.
     * - `true` options set all others to false.
     * - `false` options set all others to true.
     * - Use the `$default` parameter to set value for all if none are set.
     *
     * @param array<string, ?bool> $array   Array of options, `get_defined_vars()` is recommended
     * @param bool                 $default Default value for all options
     *
     * @return array<string, bool>
     */
    function booleanValues( array $array, bool $default = true ) : array
    {
        // Isolate the options
        $array = \array_filter( $array, static fn( $value ) => \is_bool( $value ) );

        // If any option is true, set all others to false
        if ( \in_array( true, $array, true ) ) {
            return \array_map( static fn( $option ) => $option === true, $array );
        }

        // If any option is false, set all others to true
        if ( \in_array( false, $array, true ) ) {
            return \array_map( static fn( $option ) => $option != false, $array );
        }

        // If none are true or false, set all to the default
        return \array_map( static fn( $option ) => $default, $array );
    }

    // <editor-fold desc="Class Functions">

    /**
     * Returns the name of an object or callable.
     *
     * @param mixed $callable
     * @param bool  $validate [optional] ensure the `class_exists`
     *
     * @return ($validate is true ? array{0: class-string, 1: string} : array{0: string, 1: string})
     */
    function explode_class_callable( mixed $callable, bool $validate = false ) : array
    {
        if ( \is_array( $callable ) && \count( $callable ) === 2 ) {
            $class  = $callable[0];
            $method = $callable[1];
        }
        elseif ( \is_string( $callable ) && \str_contains( $callable, '::' ) ) {
            [$class, $method] = \explode( '::', $callable );
        }
        else {
            throw new InvalidArgumentException( 'The provided callable must be a string or an array.' );
        }

        \assert( \is_string( $class ) && \is_string( $method ) );

        // Check existence if $validate is true
        if ( $validate && ! \class_exists( $class ) ) {
            throw new InvalidArgumentException( message : 'Class '.$class.' does not exists.' );
        }

        return [
            $class,
            $method,
        ];
    }

    #[Deprecated( 'Use class_string' )]
    function get_class_string( object|string $class ) : string
    {
        return \is_object( $class ) ? $class::class : $class;
    }

    /**
     * Returns the name of an object or callable.
     *
     * @param callable|callable-string|class-string|string $from
     * @param bool                                         $validate [optional] ensure the `class_exists`
     *
     * @return ($validate is true ? class-string : ?string)
     */
    function get_class_name( mixed $from, bool $validate = false ) : ?string
    {
        // array callables [new SomeClass, 'method']
        if ( \is_array( $from ) && isset( $from[0] ) && \is_object( $from[0] ) ) {
            $from = $from[0]::class;
        }

        // Handle direct objects
        if ( \is_object( $from ) ) {
            $from = $from::class;
        }

        // The [callable] type should have been handled by the two previous checks
        if ( ! \is_string( $from ) ) {
            if ( $validate ) {
                $message = __METHOD__.' was passed an unresolvable class of type '.\gettype( $from ).'.';
                throw new InvalidArgumentException( $message );
            }
            return null;
        }

        // Handle class strings
        $class = \str_contains( $from, '::' ) ? \explode( '::', $from, 2 )[0] : $from;

        // Check existence if $validate is true
        if ( $validate && ! \class_exists( $class ) ) {
            throw new InvalidArgumentException( message : 'Class '.$class.' does not exists.' );
        }

        return $class;
    }

    /**
     * @template T_Class of object
     * @template T_Interface of object
     *
     * @param class-string<T_Class>|string     $class     Check if this class implements a given Interface
     * @param class-string<T_Interface>|string $interface The Interface to check against
     *
     * @phpstan-assert-if-true class-string<T_Interface> $interface
     *
     * @return bool
     */
    function implements_interface( string $class, string $interface ) : bool
    {
        if ( ! \class_exists( $class, false ) || ! \interface_exists( $interface ) ) {
            return false;
        }

        $interfaces = \class_implements( $class );

        if ( ! $interface ) {
            return false;
        }

        return \in_array( $interface, $interfaces, true );
    }

    /**
     * @param class-string|object|string $class     Check if this class uses a given Trait
     * @param class-string|object|string $trait     The Trait to check against
     * @param bool                       $recursive [false] Also check for Traits using Traits
     *
     * @return bool
     */
    function uses_trait( string|object $class, string|object $trait, bool $recursive = false ) : bool
    {
        if ( \is_object( $trait ) ) {
            $trait = $trait::class;
        }

        $traits = get_traits( $class );

        if ( $recursive ) {
            foreach ( $traits as $traitClass ) {
                $traits += get_traits( $traitClass );
            }
        }

        return \in_array( $trait, $traits, true );
    }

    /**
     * @param class-string|object|string $class
     *
     * @return array<string, class-string>
     */
    function get_traits( string|object $class ) : array
    {
        if ( \is_object( $class ) ) {
            $class = $class::class;
        }

        $traits = \class_uses( $class );

        foreach ( \class_parents( $class ) ?: [] as $parent ) {
            $traits += \class_uses( $parent );
        }

        return $traits;
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
    #[Deprecated( 'Use ClassInfo', [ClassInfo::class, 'basename'] )]
    function classBasename( string|object $class, ?string $filter = 'strtolower' ) : string
    {
        $namespaced = \explode( '\\', \is_object( $class ) ? $class::class : $class );
        $basename   = \end( $namespaced );

        if ( \is_callable( $filter ) ) {
            return $filter( $basename );
        }

        return $basename;
    }

    /**
     * # Get all the classes, traits, and interfaces used by a class.
     *
     * @param class-string|object|string $class
     * @param bool                       $includeSelf
     * @param bool                       $includeInterface
     * @param bool                       $includeTrait
     * @param bool                       $namespace
     * @param bool                       $details
     *
     * @return array<array-key, string>
     */
    function extendingClasses(
        string|object $class,
        bool          $includeSelf = true,
        bool          $includeInterface = true,
        bool          $includeTrait = true,
        bool          $namespace = true,
        bool          $details = false,
    ) : array {
        $class = \is_object( $class ) ? $class::class : $class;

        $classes = $includeSelf ? [$class => 'self'] : [];

        $parent = \class_parents( $class ) ?: [];
        $classes += \array_fill_keys( $parent, 'parent' );

        if ( $includeInterface ) {
            $interfaces = \class_implements( $class ) ?: [];
            $classes += \array_fill_keys( $interfaces, 'interface' );
        }

        if ( $includeTrait ) {
            $traits = \class_uses( $class ) ?: [];
            $classes += \array_fill_keys( $traits, 'trait' );
        }

        if ( $details ) {
            return $classes;
        }

        $classes = \array_keys( $classes );

        if ( $namespace ) {
            foreach ( $classes as $key => $class ) {
                $classes[$key] = classBasename( $class );
            }
        }

        return $classes;
    }
    // </editor-fold>
}

namespace Assert {

    use const Support\URL_SAFE_CHARACTERS_UNICODE;

    /**
     * Ensures the provided variable exists as a class.
     *
     * @param mixed $class
     *
     * @return bool
     */
    function class_exists( mixed $class ) : bool
    {
        \assert( \is_string( $class ) || \is_object( $class ) );

        \assert( \class_exists( \is_object( $class ) ? $class::class : $class ) );

        return true;
    }

    /**
     * @param mixed $value
     * @param bool  $nullable
     *
     * @return ($nullable is true ? null|string : string)
     */
    function as_string( mixed $value, bool $nullable = false ) : ?string
    {
        \assert( \is_string( $value ) || ( $nullable && \is_null( $value ) ) );

        return $value;
    }

    /**
     * @param mixed $value
     * @param bool  $is_list
     *
     * @return array<array-key, mixed>
     */
    function as_array( mixed $value, bool $is_list = false ) : array
    {
        \assert( \is_array( $value ) );
        if ( $is_list ) {
            \assert( \array_is_list( $value ) );
        }
        return $value;
    }


    /**
     * @param null|string|\Stringable $value
     * @param string                  ...$enforceDomain
     *
     * @return bool
     */
    function isEmail( null|string|\Stringable $value, string ...$enforceDomain ) : bool
    {
        // Can not be null or an empty string
        if ( ! $string = (string) $value ) {
            return false;
        }

        // Emails are case-insensitive, lowercase the $value for processing
        $string = \strtolower( $string );

        // Must contain an [at] and at least one period
        if ( ! \str_contains( $string, '@' ) || ! \str_contains( $string, '.' ) ) {
            return false;
        }

        // Must end with a letter
        if ( ! \preg_match( '/[a-z]/', $string[-1] ) ) {
            return false;
        }

        // Must only contain valid characters
        if ( \preg_match( '/[^'.URL_SAFE_CHARACTERS_UNICODE.']/u', $string ) ) {
            return false;
        }

        // Validate domains, if specified
        foreach ( $enforceDomain as $domain ) {
            if ( \str_ends_with( $string, \strtolower( $domain ) ) ) {
                return true;
            }
        }

        return true;
    }
}

namespace String {

    use Support\{Escape, Normalize};
    use Random\RandomException;
    use function Support\getProjectRootDirectory;
    use const Support\{AUTO, EMPTY_STRING, URL_SAFE_CHARACTERS_UNICODE};

    // <editor-fold desc="Key Functions">

    function createKey() : string
    {
        try {
            return \hash( algo : 'xxh3', data : \random_bytes( 7 ) );
        }
        catch ( RandomException $e ) {
            return \hash( algo : 'xxh3', data : (string) \rand( 0, PHP_INT_MAX ) );
        }
    }

    function implodeKey( mixed $value = AUTO, string $separator = ':' ) : string
    {
        $key = [];

        if ( ! \is_iterable( $value ) ) {
            $value = [$value];
        }

        foreach ( $value as $segment ) {
            if ( \is_null( $segment ) ) {
                continue;
            }

            $key[] = match ( \gettype( $segment ) ) {
                'string'  => $segment,
                'boolean' => $segment ? 'true' : 'false',
                'integer' => (string) $segment,
                'object'  => $segment::class.'#'.\spl_object_id( $segment ),
                default   => \hash(
                    algo : 'xxh3',
                    data : \json_encode( $value ) ?: \serialize( $value ),
                ),
            };
        }

        return \implode( $separator, $key );
    }

    /**
     * # Generate a deterministic key from a value.
     *
     *  - `$value` will be stringified using `json_encode()`.
     *
     * @param mixed ...$value
     *
     * @return string
     */
    function encodeKey( mixed ...$value ) : string
    {
        return \json_encode( $value, 64 | 256 | 512 )
                ?: throw new \InvalidArgumentException( 'Key cannot be encoded: '.\json_last_error_msg() );
    }

    /**
     * @param mixed ...$value
     */
    function cacheKey( mixed ...$value ) : string
    {
        $key = [];

        foreach ( $value as $segment ) {
            if ( \is_null( $segment ) ) {
                continue;
            }

            $key[] = match ( \gettype( $segment ) ) {
                'string'  => $segment,
                'boolean' => $segment ? 'true' : 'false',
                'integer' => (string) $segment,
                default   => \hash(
                    algo : 'xxh3',
                    data : \json_encode( $value ) ?: \serialize( $value ),
                ),
            };
        }

        return \implode( ':', $key );
    }

    /**
     * # Generate a deterministic hash key from a value.
     *
     *  - `$value` will be stringified using `json_encode()` by default.
     *  - The value is hashed using `xxh3`.
     *  - The hash is not reversible.
     *
     * The $value can be stringified in one of the following ways:
     *
     * ## `json`
     * Often the fastest option when passing a large object.
     * Will fall back to `serialize` if `json_encode()` fails.
     *
     * ## `serialize`
     * Can sometimes be faster for arrays of strings.
     *
     * ## `implode`
     * Very fast for simple arrays of strings.
     * Requires the `$value` to be an `array` of `string|int|float|bool|Stringable`.
     * Nested arrays are not supported.
     *
     * ```
     * hashKey( [ 'example', new stdClass(), true ] );
     * // => a0a42b9a3a72e14c
     * ```
     *
     * @param mixed                        $value
     * @param 'implode'|'json'|'serialize' $encoder
     *
     * @return string 16 character hash of the value
     */
    function hashKey(
        mixed  $value,
        string $encoder = 'json',
    ) : string {
        if ( ! \is_string( $value ) ) {
            // Use serialize if defined
            if ( $encoder === 'serialize' ) {
                $value = \serialize( $value );
            }
            // Implode if defined and $value is an array
            elseif ( $encoder === 'implode' && \is_array( $value ) ) {
                $value = \implode( ':', $value );
            }
            // JSON as default, or as fallback
            else {
                $value = \json_encode( $value ) ?: \serialize( $value );
            }
        }

        // Hash the $value to a 16 character string
        return \hash( algo : 'xxh3', data : $value );
    }

    /**
     * # Generate a deterministic key from a system path string.
     *
     * The `$source` will be pass through {@see normalizeKey()}.
     *
     * If the resulting key starts with a normalized {@see getProjectRootDirectory()} string,
     * the returned key will start from the project root.
     *
     *  ```
     *  sourceKey( '/var/www/project/vendor/package/example.file' );
     *  // => 'vendor-package-example-file'
     *  ```
     *
     * @param string|\Stringable $source
     * @param string             $separator [-]
     * @param ?string            $fromRoot
     *
     * @return string
     */
    function sourceKey(
        string|\Stringable $source,
        string             $separator = '-',
        ?string            $fromRoot = null,
    ) : string {
        // Can not be null or an empty string
        if ( ! $string = (string) $source ) {
            return EMPTY_STRING;
        }

        static $rootKey;
        $rootKey[$separator] ??= Normalize::key(
            [getProjectRootDirectory(), $fromRoot],
            $separator,
        );

        $key = Normalize::key( $string, $separator );

        if ( \str_starts_with( $key, $rootKey[$separator] ) ) {
            return \substr( $key, \strlen( $rootKey[$separator] ) + 1 );
        }

        return $key;
    }

    // </editor-fold>

    // <editor-fold desc="Filters and Escapes">

    // <editor-fold desc="URL">

    /**
     * @param null|string|\Stringable $string       $string
     * @param bool                    $preserveTags
     *
     * @return string
     * @deprecated `\Support\Escape::url( .., .., )`
     *
     * Filter a string assuming it a URL.
     *
     * - Preserves Unicode characters.
     * - Removes tags by default.
     */
    function filterUrl( null|string|\Stringable $string, bool $preserveTags = false ) : string
    {
        // Can not be null or an empty string
        if ( ! $string = (string) $string ) {
            return EMPTY_STRING;
        }
        trigger_deprecation( 'Northrook\\Functions', 'dev', __METHOD__ );
        static $cache = [];

        return $cache[\json_encode( [$string, $preserveTags], 832 )] ??= (
            static function() use ( $string, $preserveTags ) : string {
                $safeCharacters = URL_SAFE_CHARACTERS_UNICODE;

                if ( $preserveTags ) {
                    $safeCharacters .= '{}|^`"><@';
                }

                return \preg_replace(
                    pattern     : "/[^{$safeCharacters}]/u",
                    replacement : EMPTY_STRING,
                    subject     : $string,
                ) ?? EMPTY_STRING;
            }
        )();
    }

    /**
     * @param null|string|\Stringable $string
     *
     * @return string
     * @deprecated  `\Support\Escape::url()`
     *              Sanitizes string for use inside href attribute
     */
    function escapeUrl( null|string|\Stringable $string ) : string
    {
        trigger_deprecation( 'Northrook\\Functions', 'probing', __METHOD__ );
        // Sanitize the URL, preserving tags for escaping
        $string = filterUrl( (string) $string, true );

        // Escape special characters including tags
        return \htmlspecialchars( $string, ENT_QUOTES, 'UTF-8' );
    }

    // </editor-fold>

    /**
     * @param string $string
     *
     * @return string
     * @deprecated `\Support\Escape::each()`
     *
     * Escape each and every character in the provided string.
     *
     * ```
     *  escapeCharacters('Hello!');
     *  // => '\H\e\l\l\o\!'
     * ```
     */
    function escapeCharacters( string $string ) : string
    {
        return \implode( '', \array_map( static fn( $char ) => '\\'.$char, \str_split( $string ) ) );
    }

    function stripTags(
        null|string|\Stringable $string,
        string                  $replacement = ' ',
        ?string              ...$allowed_tags,
    ) : string {
        return \str_replace(
            '  ',
            ' ',
            \strip_tags(
                \str_replace( '<', "{$replacement}<", (string) $string ),
            ),
        );
    }

    /**
     * Escapes string for use inside iCal template.
     *
     * @param null|string|\Stringable $value
     *
     * @return string
     */
    function escapeICal( null|string|\Stringable $value ) : string
    {
        // Can not be null or an empty string
        if ( ! ( $string = (string) $value ) ) {
            return EMPTY_STRING;
        }

        trigger_deprecation( 'Northrook\\Functions', 'probing', __METHOD__ );
        // https://www.ietf.org/rfc/rfc5545.txt
        $string = \str_replace( "\r", '', $string );
        $string = \preg_replace( '#[\x00-\x08\x0B-\x1F]#', "\u{FFFD}", (string) $string );

        return \addcslashes( (string) $string, "\";\\,:\n" );
    }

    // </editor-fold>

    /**
     * Throws a {@see \LengthException} when the length of `$string` exceeds the provided `$limit`.
     *
     * @param string      $string
     * @param int         $limit
     * @param null|string $caller Class, method, or function name
     *
     * @return void
     */
    function characterLimit(
        string  $string,
        int     $limit,
        ?string $caller = null,
    ) : void {
        $limit  = \PHP_MAXPATHLEN - 2;
        $length = \strlen( $string );
        if ( $length > $limit ) {
            if ( $caller ) {
                $message = $caller." resulted in a {$length} character string, exceeding the {$limit} limit.";
            }
            else {
                $message = "The provided string is {$length} characters long, exceeding the {$limit} limit.";
            }

            throw new \LengthException( $message );
        }
    }
}
