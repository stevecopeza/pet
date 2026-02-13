<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Event;

use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Event\DomainEvent;

class QuoteAccepted implements DomainEvent
{
    private Quote $quote;
    private \DateTimeImmutable $occurredAt;

    public function __construct(Quote $quote)
    {
        $this->quote = $quote;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function quote(): Quote
    {
        return $this->quote;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
