<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Support\Entity\Ticket;
use DateTimeImmutable;

class TicketAssigned implements DomainEvent
{
    private Ticket $ticket;
    private ?string $assignedAgentId;
    private DateTimeImmutable $occurredAt;

    public function __construct(Ticket $ticket, ?string $assignedAgentId)
    {
        $this->ticket = $ticket;
        $this->assignedAgentId = $assignedAgentId;
        $this->occurredAt = new DateTimeImmutable();
    }

    public function ticket(): Ticket
    {
        return $this->ticket;
    }

    public function assignedAgentId(): ?string
    {
        return $this->assignedAgentId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
