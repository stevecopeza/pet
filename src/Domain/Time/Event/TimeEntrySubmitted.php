<?php

declare(strict_types=1);

namespace Pet\Domain\Time\Event;

use Pet\Domain\Event\DomainEvent;

class TimeEntrySubmitted implements DomainEvent
{
    private int $timeEntryId;
    private int $employeeId;
    private int $minutes;
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        int $timeEntryId,
        int $employeeId,
        int $minutes
    ) {
        $this->timeEntryId = $timeEntryId;
        $this->employeeId = $employeeId;
        $this->minutes = $minutes;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function timeEntryId(): int
    {
        return $this->timeEntryId;
    }
}
