<?php

declare(strict_types=1);

namespace Support\Interface;

/**
 * The primary `action` must be through the `__invoke` method.
 *
 * ```
 * #[Route( '/{route}' )]
 * public function controllerMethod( string $route, Service $action ) : void {
 *     $action( 'route action!' );
 * }
 * ```
 *
 * @method __invoke()
 *
 * @author Martin Nielsen <mn@northrook.com>
 */
interface ActionInterface
{
}
