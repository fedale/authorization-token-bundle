<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Infrastructure\EventDispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Thin wrapper around a PSR-14 event dispatcher.
 *
 * Domain events are dispatched by their class, so applications subscribe with
 * the standard Symfony EventListener/EventSubscriber mechanism. The wrapper
 * keeps the application services decoupled from the dispatcher implementation
 * and gives a single seam to enrich or buffer events later if needed.
 */
final class DomainEventDispatcher
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function dispatch(object $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
