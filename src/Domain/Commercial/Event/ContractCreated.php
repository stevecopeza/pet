<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Event;

use Pet\Domain\Event\DomainEvent;
use Pet\Domain\Commercial\Entity\Contract;

class ContractCreated implements DomainEvent
{
    private Contract $contract;
    private \DateTimeImmutable $occurredAt;

    public function __construct(Contract $contract)
    {
        $this->contract = $contract;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function contract(): Contract
    {
        return $this->contract;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
