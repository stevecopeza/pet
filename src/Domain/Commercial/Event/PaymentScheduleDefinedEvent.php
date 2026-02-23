<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Event;

use Pet\Domain\Event\DomainEvent;

final class PaymentScheduleDefinedEvent implements DomainEvent
{
    private int $quoteId;
    private float $totalAmount;
    private array $items;
    private \DateTimeImmutable $occurredAt;

    /**
     * @param array $items Array of ['id' => int|null, 'title' => string, 'amount' => float, 'dueDate' => ?\DateTimeImmutable]
     */
    public function __construct(int $quoteId, float $totalAmount, array $items)
    {
        $this->quoteId = $quoteId;
        $this->totalAmount = $totalAmount;
        $this->items = $items;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }

    public function totalAmount(): float
    {
        return $this->totalAmount;
    }

    public function items(): array
    {
        return $this->items;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}

