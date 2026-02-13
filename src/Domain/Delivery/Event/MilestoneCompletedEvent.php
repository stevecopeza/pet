<?php

declare(strict_types=1);

namespace Pet\Domain\Delivery\Event;

use Pet\Domain\Event\DomainEvent;

class MilestoneCompletedEvent implements DomainEvent
{
    private int $projectId;
    private string $milestoneTitle;
    private \DateTimeImmutable $occurredAt;

    public function __construct(int $projectId, string $milestoneTitle)
    {
        $this->projectId = $projectId;
        $this->milestoneTitle = $milestoneTitle;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function projectId(): int
    {
        return $this->projectId;
    }

    public function milestoneTitle(): string
    {
        return $this->milestoneTitle;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
