<?php

namespace Support;

trait ClassMethods
{
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
    final protected function classBasename( ?string $class = null ) : string
    {
        return classBasename( $class ?? $this::class );
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
    final protected function extendingClasses(
        string|object $class,
        bool          $includeSelf = true,
        bool          $includeInterface = true,
        bool          $includeTrait = true,
        bool          $namespace = true,
        bool          $details = false,
    ) : array {
        return extendingClasses( $class, $includeSelf, $includeInterface, $includeTrait, $namespace, $details );
    }
}
