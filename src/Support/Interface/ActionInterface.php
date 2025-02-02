<?php

declare(strict_types=1);

namespace Support\Interface;

use JetBrains\PhpStorm\Deprecated;

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
#[Deprecated]
interface ActionInterface {}
