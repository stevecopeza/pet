<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Support\Entity\Ticket;

class TicketCreated implements DomainEvent
{
    private $ticket;
    private $occurredAt;

    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function ticket(): Ticket
    {
        return $this->ticket;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
