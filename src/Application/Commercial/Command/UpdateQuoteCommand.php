<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class UpdateQuoteCommand
{
    private int $id;
    private int $customerId;
    private float $totalValue;
    private string $currency;
    private ?\DateTimeImmutable $acceptedAt;
    private array $malleableData;

    public function __construct(
        int $id, 
        int $customerId, 
        float $totalValue = 0.00,
        string $currency = 'USD',
        ?\DateTimeImmutable $acceptedAt = null,
        array $malleableData = []
    ) {
        $this->id = $id;
        $this->customerId = $customerId;
        $this->totalValue = $totalValue;
        $this->currency = $currency;
        $this->acceptedAt = $acceptedAt;
        $this->malleableData = $malleableData;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function totalValue(): float
    {
        return $this->totalValue;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function acceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function malleableData(): array
    {
        return $this->malleableData;
    }
}
