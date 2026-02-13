<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Event;

use Pet\Domain\Event\DomainEvent;

class TicketBreachedEvent implements DomainEvent
{
    private int $ticketId;
    private \DateTimeImmutable $occurredAt;

    public function __construct(int $ticketId)
    {
        $this->ticketId = $ticketId;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getTicketId(): int
    {
        return $this->ticketId;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
