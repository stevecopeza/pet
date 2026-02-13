<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Event;

use Pet\Domain\Event\DomainEvent;

class EscalationTriggeredEvent implements DomainEvent
{
    private int $ticketId;
    private int $stage;
    private \DateTimeImmutable $occurredAt;

    public function __construct(int $ticketId, int $stage)
    {
        $this->ticketId = $ticketId;
        $this->stage = $stage;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function ticketId(): int
    {
        return $this->ticketId;
    }

    public function stage(): int
    {
        return $this->stage;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
