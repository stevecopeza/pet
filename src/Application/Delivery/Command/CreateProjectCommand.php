<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Command;

class CreateProjectCommand
{
    private int $customerId;
    private string $name;
    private float $soldHours;
    private ?int $sourceQuoteId;

    public function __construct(
        int $customerId,
        string $name,
        float $soldHours,
        ?int $sourceQuoteId = null
    ) {
        $this->customerId = $customerId;
        $this->name = $name;
        $this->soldHours = $soldHours;
        $this->sourceQuoteId = $sourceQuoteId;
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function soldHours(): float
    {
        return $this->soldHours;
    }

    public function sourceQuoteId(): ?int
    {
        return $this->sourceQuoteId;
    }
}
