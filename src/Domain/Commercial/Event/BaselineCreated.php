<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Commercial\Entity\Baseline;

class BaselineCreated implements DomainEvent
{
    private Baseline $baseline;
    private \DateTimeImmutable $occurredAt;

    public function __construct(Baseline $baseline)
    {
        $this->baseline = $baseline;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function baseline(): Baseline
    {
        return $this->baseline;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}

