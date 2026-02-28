<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\Event;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Event\EventBus;
use Pet\Domain\Event\Repository\EventStreamRepository;
use Pet\Domain\Event\SourcedEvent;
use Pet\Infrastructure\Event\PersistingEventBus;

class PersistingEventBusTest extends TestCase
{
    private $repo;
    private $inner;
    private $bus;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(EventStreamRepository::class);
        $this->inner = $this->createMock(EventBus::class);
        $this->bus = new PersistingEventBus($this->inner, $this->repo);
    }

    public function testDispatchesSourcedEventToRepositoryAndInnerBus(): void
    {
        $event = $this->createMock(SourcedEvent::class);
        $event->method('aggregateType')->willReturn('test_agg');
        $event->method('aggregateId')->willReturn(123);
        $event->method('toPayload')->willReturn(['foo' => 'bar']);

        $this->repo->expects($this->once())
            ->method('nextVersion')
            ->with('test_agg', 123)
            ->willReturn(5);

        $this->repo->expects($this->once())
            ->method('append')
            ->with(
                'test_agg',
                123,
                5,
                get_class($event),
                json_encode(['foo' => 'bar'])
            );

        $this->inner->expects($this->once())
            ->method('dispatch')
            ->with($event);

        $this->bus->dispatch($event);
    }

    public function testDispatchesNonSourcedEventOnlyToInnerBus(): void
    {
        $event = $this->createMock(DomainEvent::class);

        $this->repo->expects($this->never())->method('append');
        $this->inner->expects($this->once())->method('dispatch')->with($event);

        $this->bus->dispatch($event);
    }
}
