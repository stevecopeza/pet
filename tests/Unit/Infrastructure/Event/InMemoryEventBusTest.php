<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Infrastructure\Event\InMemoryEventBus;
use PHPUnit\Framework\TestCase;

class InMemoryEventBusTest extends TestCase
{
    public function testDispatchAndSubscribe(): void
    {
        $bus = new InMemoryEventBus();
        $occurred = false;

        $event = new class implements DomainEvent {
            public function occurredAt(): \DateTimeImmutable
            {
                return new \DateTimeImmutable();
            }
        };

        $bus->subscribe(get_class($event), function (DomainEvent $e) use (&$occurred) {
            $occurred = true;
        });

        $bus->dispatch($event);

        $this->assertTrue($occurred, 'Event listener should have been called.');
    }
}
