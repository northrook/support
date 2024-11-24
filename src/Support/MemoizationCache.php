<?php

declare(strict_types=1);

namespace Support;

use Psr\Log as Psr;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Contracts\Cache as Symfony;
use const Cache\EPHEMERAL;
use Throwable;
use Closure;
use LogicException;

final class MemoizationCache
{
    private static ?MemoizationCache $instance;

    /** @var array<string, array{value: mixed, hit: int}> */
    private array $inMemoryCache;

    public function __construct(
        private readonly ?Symfony\CacheInterface $cacheAdapter = null,
        private readonly ?Psr\LoggerInterface    $logger = null,
    ) {
        $this::$instance = $this;
    }

    /**
     * @param callable-string|Closure $callback
     * @param null|string             $key
     * @param null|int                $persistence
     *
     * @return mixed
     */
    public function cache( callable|Closure $callback, ?string $key = null, ?int $persistence = EPHEMERAL ) : mixed
    {
        $key ??= \hash( 'xxh3', \serialize( Reflect::function( $callback )->getClosureUsedVariables() ) );

        if ( ! $key ) {
            return $callback();
        }

        // If persistence is not requested, or if we are lacking a capable adapter
        if ( EPHEMERAL === $persistence || ! $this->cacheAdapter ) {
            if ( ! isset( $this->inMemoryCache[$key] ) ) {
                $this->inMemoryCache[$key] = [
                    'value' => $callback(),
                    'hit'   => 0,
                ];
            }
            else {
                $this->inMemoryCache[$key]['hit']++;
            }

            return $this->inMemoryCache[$key]['value'];
        }

        try {
            return $this->cacheAdapter->get(
                key      : $key,
                callback : static function( Symfony\ItemInterface $memo ) use ( $callback, $persistence ) : mixed {
                    $memo->expiresAfter( $persistence );
                    return $callback();
                },
            );
        }
        catch ( Throwable $exception ) {
            $this->logger?->error(
                'Exception thrown when using {runtime}: {message}.',
                [
                    'runtime'   => $this::class,
                    'message'   => $exception->getMessage(),
                    'exception' => $exception,
                ],
            );
            return $callback();
        }
    }

    /**
     * Retrieve the {@see MemoizationCache::$instance}, instantiating it if required.
     *
     * - To use a {@see Symfony\CacheInterface}, instantiate before making your first {@see cache()} call.
     *
     * @return MemoizationCache
     */
    public static function instance() : MemoizationCache
    {
        return MemoizationCache::$instance ?? new MemoizationCache();
    }

    /**
     * Clears the built-in memory cache.
     *
     * @return $this
     */
    public function clearInMemoryCache() : MemoizationCache
    {
        $this->inMemoryCache = [];
        return $this;
    }

    /**
     * Clears the {@see CacheInterface} if assigned.
     *
     * @return $this
     */
    public function clearAdapterCache() : MemoizationCache
    {
        if ( $this->cacheAdapter instanceof AdapterInterface ) {
            $this->cacheAdapter->clear();
        }
        else {
            $this->logger?->error( 'The provided cache adapter does not the clear() method.' );
        }
        return $this;
    }

    /**
     * @return array<string, array{value: mixed, hit: int}>
     */
    public function getInMemoryCache() : array
    {
        return $this->inMemoryCache;
    }

    /**
     * Clear the current {@see \Support\MemoizationCache::$instance}.
     *
     * ⚠️ Does _not_ reinstantiate the instance.
     *
     * @param bool $areYouSure
     *
     * @return void
     */
    public function clearStaticInstance( bool $areYouSure = false ) : void
    {
        if ( $areYouSure ) {
            $this::$instance = null;
        }

        throw new LogicException( 'Please read the '.__METHOD__.' comment before clearing the cache instance.' );
    }
}
