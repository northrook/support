<?php

declare(strict_types=1);

namespace {
    if ( ! defined( 'ENCODING' ) ) {
        define( 'ENCODING', 'UTF-8' );
    }
}

namespace Support {

    use JetBrains\PhpStorm\Pure;
    use function Assert\{isIterable, isScalar};
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
            throw new \InvalidArgumentException( message : 'Unable to create a new DateTimeImmutable object: '.$exception->getMessage(), code    : 500, previous : $exception );
        }
    }

    /**
     * Retrieves the project root directory.
     *
     * - This function assumes the Composer directory is present in the project root.
     * - The return is cached for this process.
     *
     * @return string
     */
    function getProjectRootDirectory() : string
    {
        static $projectRoot;

        return $projectRoot ??= (
            static function() : string {
                // Get an array of each directory leading to this file
                $explodeCurrentDirectory = \explode( \DIRECTORY_SEPARATOR, __DIR__ );
                $vendorDirectory         = \array_slice( $explodeCurrentDirectory, 0, -4 );

                // Implode and return the $projectRoot path
                return \implode( \DIRECTORY_SEPARATOR, $vendorDirectory );
            }
        )();
    }

    /**
     * Retrieves the system temp directory for this project.
     *
     * - A directory is named using a hash based on the projectRootDirectory.
     * - The return is cached for this process.
     *
     * @param ?string $append
     *
     * @return string
     */
    function getSystemCacheDirectory( ?string $append = null ) : string
    {
        static $systemCache;
        $path = $systemCache ??= (
            static function() : string {
                $tempDir = \sys_get_temp_dir();
                $dirHash = \hash( 'xxh3', getProjectRootDirectory() );

                return "{$tempDir}/{$dirHash}";
            }
        )();

        return Normalize::path( [$path, $append] );
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
        if ( isScalar( $value ) ) {
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
        $array = \array_filter( $array, static fn( $value ) => \is_bool( $value ) || \is_null( $value ) );

        // If any option is true, set all others to false
        if ( \in_array( true, $array, true ) ) {
            return \array_map( static fn( $option ) => true === $option, $array );
        }

        // If any option is false, set all others to true
        if ( \in_array( false, $array, true ) ) {
            return \array_map( static fn( $option ) => false !== $option, $array );
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
        if ( \is_array( $callable ) && \count( $callable ) === 1 ) {
            $class  = $callable[0];
            $method = $callable[1];
        }
        elseif ( \is_string( $callable ) ) {
            [$class, $method] = \explode( '::', $callable );
        }
        else {
            throw new \InvalidArgumentException( 'The provided callable must be a string or an array.' );
        }

        \assert( \is_string( $class ) && \is_string( $method ) );

        // Check existence if $validate is true
        if ( $validate && ! \class_exists( $class ) ) {
            throw new \InvalidArgumentException( message: 'Class '.$class.' does not exists.' );
        }

        return [
            $class,
            $method,
        ];
    }

    /**
     * @param object|string $class
     *
     * @return string
     */
    function get_class_string( object|string $class ) : string
    {
        return \is_object( $class ) ? $class::class : $class;
    }

    /**
     * Returns the name of an object or callable.
     *
     * @param mixed $from
     * @param bool  $validate [optional] ensure the `class_exists`
     *
     * @return ($validate is true ? class-string : string)
     */
    function get_class_name( mixed $from, bool $validate = false ) : string
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
        \assert( \is_string( $from ) );

        // Handle class strings
        $class = \str_contains( $from, '::' ) ? \explode( '::', $from, 2 )[0] : $from;

        // Check existence if $validate is true
        if ( $validate && ! \class_exists( $class ) ) {
            throw new \InvalidArgumentException( message: 'Class '.$class.' does not exists.' );
        }

        return $class;
    }

    /**
     * @param class-string|object $class     Check if this class uses a given Trait
     * @param class-string|object $trait     The Trait to check against
     * @param bool                $recursive [false] Also check for Traits using Traits
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
     * @param class-string|object $class
     *
     * @return array<string, class-string>
     */
    function get_traits( string|object $class ) : array
    {
        if ( \is_object( $class ) ) {
            $class = $class::class;
        }

        $traits = \class_uses( $class );

        foreach ( \class_parents( $class ) as $parent ) {
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
     *
     * @return string
     */
    function classBasename( string|object $class ) : string
    {
        $class     = \is_object( $class ) ? $class::class : $class;
        $namespace = \strrpos( $class, '\\' );

        return $namespace ? \substr( $class, ++$namespace ) : $class;
    }

    /**
     * # Get all the classes, traits, and interfaces used by a class.
     *
     * @param object|string $class
     * @param bool          $includeSelf
     * @param bool          $includeInterface
     * @param bool          $includeTrait
     * @param bool          $namespace
     * @param bool          $details
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
     * @param mixed  $class
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
     * Check whether the script is being executed from a command line.
     */
    function isCLI() : bool
    {
        return PHP_SAPI === 'cli' || \defined( 'STDIN' );
    }

    /**
     * Checks whether OPcache is installed and enabled for the given environment.
     */
    function OPcacheEnabled() : bool
    {
        // Ensure OPcache is installed and not disabled
        if (
            ! \function_exists( 'opcache_invalidate' )
            || ! \ini_get( 'opcache.enable' )
        ) {
            return false;
        }

        // If called from CLI, check accordingly, otherwise true
        return ! isCLI() || \ini_get( 'opcache.enable_cli' );
    }

    /**
     * False if passed value is considered `null` and `empty` type values, retains `0` and `false`.
     *
     * @phpstan-assert-if-true scalar $value
     *
     * @param mixed $value
     *
     * @return bool
     */
    function isEmpty( mixed $value ) : bool
    {
        // If it is a boolean, it cannot be empty
        if ( \is_bool( $value ) ) {
            return false;
        }

        if ( \is_numeric( $value ) ) {
            return false;
        }

        return empty( $value );
    }

    /**
     * # Determine if a value is a scalar.
     *
     * @phpstan-assert-if-true scalar|\Stringable|null $value
     *
     * @param mixed $value
     *
     * @return bool
     */
    function isScalar( mixed $value ) : bool
    {
        return \is_scalar( $value ) || $value instanceof \Stringable || \is_null( $value );
    }

    /**
     * `is_iterable` implementation that also checks for {@see \ArrayAccess}.
     *
     * @phpstan-assert-if-true iterable|\Traversable $value
     *
     * @param mixed $value
     *
     * @return bool
     */
    function isIterable( mixed $value ) : bool
    {
        return \is_iterable( $value ) || $value instanceof \ArrayAccess;
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

    /**
     * Checks if a given value has a URL structure.
     *
     * ⚠️ Does **NOT** validate the URL in any capacity!
     *
     * @param mixed   $value
     * @param ?string $requiredProtocol
     *
     * @return bool
     */
    function isUrl( mixed $value, ?string $requiredProtocol = null ) : bool
    {
        // Stringify scalars and Stringable objects
        $string = isScalar( $value ) ? \trim( (string) $value ) : false;

        // Can not be an empty string
        if ( ! $string ) {
            return false;
        }

        // Must not start with a number
        if ( \is_numeric( $string[0] ) ) {
            return false;
        }

        /**
         * Does the string resemble a URL-like structure?
         *
         * Ensures the string starts with a schema-like substring, and has a real-ish domain extension.
         *
         * - Will gladly accept bogus strings like `not-a-schema://d0m@!n.tld/`
         */
        if ( ! \preg_match( '#^([\w\-+]*?[:/]{2}).+\.[a-z0-9]{2,}#m', $string ) ) {
            return false;
        }

        // Check for required protocol if requested
        return ! ( $requiredProtocol && ! \str_starts_with( $string, \rtrim( $requiredProtocol, ':/' ).'://' ) );
    }
}

namespace String {

    use Support\Normalize;
    use function Support\getProjectRootDirectory;
    use const Support\{EMPTY_STRING, ENCODE_ESCAPE_JSON, FILTER_STRING_COMMENTS,  URL_SAFE_CHARACTERS_UNICODE};

    // <editor-fold desc="Key Functions">

    /**
     * # Generate a deterministic key from a value.
     *
     *  - `$value` will be stringified using `json_encode()`.
     * @param  mixed  ...$value
     * @return string
     */
    function encodeKey( mixed ...$value ) : string
    {
        return \json_encode( $value, 64 | 256 | 512 )
                ?: throw new \InvalidArgumentException( 'Key cannot be encoded: '.\json_last_error_msg() );
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
        // Use serialize if defined
        if ( 'serialize' === $encoder ) {
            $value = \serialize( $value );
        }
        // Implode if defined and $value is an array
        elseif ( 'implode' === $encoder && \is_array( $value ) ) {
            $value = \implode( ':', $value );
        }
        // JSON as default, or as fallback
        else {
            $value = \json_encode( $value ) ?: \serialize( $value );
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

    // <editor-fold desc="Escapes and Filters">
    //
    // Filter: safe string, may contain valid HTML
    // Escape: safe string, HTML entities encoded

    /**
     * Performs {@see \htmlspecialchars} on the provided string,
     * and converts template comments to HTML comments.
     *
     * @param null|string|\Stringable $string
     * @param non-empty-string        $encoding
     *
     * @return string
     */
    function filterHtml( null|string|\Stringable $string, string $encoding = 'UTF-8' ) : string
    {
        // Can not be null or an empty string
        if ( ! $string = (string) $string ) {
            return EMPTY_STRING;
        }

        $string = \htmlspecialchars( $string, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, $encoding );

        return \strtr( $string, FILTER_STRING_COMMENTS );
    }

    /**
     * Escapes string using {@see \htmlentities}.
     *
     * @param null|string|\Stringable $string
     * @param non-empty-string        $encoding
     *
     * @return string
     */
    function escapeHtml( null|string|\Stringable $string, string $encoding = 'UTF-8' ) : string
    {
        // Can not be null or an empty string
        if ( ! $string = (string) $string ) {
            return EMPTY_STRING;
        }

        return \htmlentities( $string, ENT_QUOTES | ENT_HTML5, $encoding );
    }

    /**
     * Escapes string for use inside CSS template.
     *
     * @param null|string|\Stringable $string
     *
     * @return string
     *
     * @see http://www.w3.org/TR/2006/WD-CSS21-20060411/syndata.html#q6 W3C CSS Characters and case reference
     */
    function escapeCSS( null|string|\Stringable $string ) : string
    {
        trigger_deprecation( 'Northrook\\Functions', 'probe', __METHOD__ );
        // http://www.w3.org/TR/2006/WD-CSS21-20060411/syndata.html#q6

        // Can not be null or an empty string
        if ( ! $string = (string) $string ) {
            return EMPTY_STRING;
        }

        return \addcslashes( $string, "\x00..\x1F!\"#$%&'()*+,./:;<=>?@[\\]^`{|}~" );
    }

    /**
     * Escapes variables for use inside <script>.
     *
     * @param mixed $value
     *
     * @return string
     */
    function escapeJS( mixed $value ) : string
    {
        trigger_deprecation( 'Northrook\\Functions', 'probe', __METHOD__ );

        $json = \json_encode( $value, ENCODE_ESCAPE_JSON );
        if ( \json_last_error() ) {
            throw new \RuntimeException( \json_last_error_msg() );
        }

        if ( ! $json ) {
            return EMPTY_STRING;
        }

        return \str_replace( [']]>', '<!', '</'], [']]\u003E', '\u003C!', '<\/'], $json );
    }

    /**
     * Escapes string for use inside HTML attribute value.
     *
     * @param null|string|\Stringable $string
     * @param bool                    $double
     * @param string                  $encoding
     *
     * @return string
     */
    function escapeHtmlAttr(
        null|string|\Stringable $string,
        bool                    $double = true,
        string                  $encoding = 'UTF-8',
    ) : string {
        trigger_deprecation( 'Northrook\\Functions', 'dev', __METHOD__ );
        // Can not be null or an empty string
        if ( ! $string = (string) $string ) {
            return EMPTY_STRING;
        }

        if ( \str_contains( $string, '`' ) && \strpbrk( $string, ' <>"\'' ) === false ) {
            $string .= ' '; // protection against innerHTML mXSS vulnerability nette/nette#1496
        }

        $string = \htmlspecialchars( $string, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, $encoding, $double );

        return \str_replace( '{', '&#123;', $string );
    }

    // </editor-fold>

    // <editor-fold desc="Filters and Escapes">

    // <editor-fold desc="URL">

    /**
     * Filter a string assuming it a URL.
     *
     * - Preserves Unicode characters.
     * - Removes tags by default.
     *
     * @param null|string|\Stringable $string       $string
     * @param bool                    $preserveTags
     *
     * @return string
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
     * Sanitizes string for use inside href attribute.
     *
     * @param null|string|\Stringable $string
     *
     * @return string
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
     * Escape each and every character in the provided string.
     *
     * ```
     *  escapeCharacters('Hello!');
     *  // => '\H\e\l\l\o\!'
     * ```
     *
     * @param string $string
     *
     * @return string
     */
    function escapeCharacters( string $string ) : string
    {
        return \implode( '', \array_map( static fn( $char ) => '\\'.$char, \str_split( $string ) ) );
    }

    function stripTags( null|string|\Stringable $string, string $replacement = ' ', ?string ...$allowed_tags ) : string
    {
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
            throw new \LengthException( $caller ? $caller." resulted in a {$length} character string, exceeding the {$limit} limit." : "The provided string is {$length} characters long, exceeding the {$limit} limit." );
        }
    }
}
