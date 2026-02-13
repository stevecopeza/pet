<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Event;

use Pet\Domain\Commercial\Entity\CostAdjustment;
use Pet\Domain\Event\DomainEvent;

class ChangeOrderApprovedEvent implements DomainEvent
{
    private CostAdjustment $costAdjustment;
    private \DateTimeImmutable $occurredAt;

    public function __construct(CostAdjustment $costAdjustment)
    {
        $this->costAdjustment = $costAdjustment;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function costAdjustment(): CostAdjustment
    {
        return $this->costAdjustment;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
