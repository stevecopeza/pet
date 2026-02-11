<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity;

use Pet\Domain\Commercial\ValueObject\QuoteState;

class Quote
{
    private ?int $id;
    private int $customerId;
    private QuoteState $state;
    private int $version;
    private float $totalValue;
    private ?string $currency;
    private ?\DateTimeImmutable $acceptedAt;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;

    /**
     * @var QuoteLine[]
     */
    private array $lines = [];

    private ?\DateTimeImmutable $archivedAt;
    private array $malleableData;

    public function __construct(
        int $customerId,
        QuoteState $state,
        int $version = 1,
        float $totalValue = 0.00,
        ?string $currency = 'USD',
        ?\DateTimeImmutable $acceptedAt = null,
        ?int $id = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $archivedAt = null,
        array $lines = [],
        array $malleableData = []
    ) {
        $this->id = $id;
        $this->customerId = $customerId;
        $this->state = $state;
        $this->version = $version;
        $this->totalValue = $totalValue;
        $this->currency = $currency;
        $this->acceptedAt = $acceptedAt;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt;
        $this->archivedAt = $archivedAt;
        $this->lines = $lines;
        $this->malleableData = $malleableData;
    }
    
    public function malleableData(): array
    {
        return $this->malleableData;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function state(): QuoteState
    {
        return $this->state;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function totalValue(): float
    {
        return $this->totalValue;
    }

    public function currency(): ?string
    {
        return $this->currency;
    }

    public function acceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }
    
    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function archivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    /**
     * @return QuoteLine[]
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function addLine(QuoteLine $line): void
    {
        if ($this->state->isTerminal()) {
            throw new \DomainException('Cannot add lines to a finalized quote.');
        }
        $this->lines[] = $line;
    }

    public function send(): void
    {
        $this->transitionTo(QuoteState::sent());
    }

    public function update(
        int $customerId, 
        float $totalValue,
        string $currency,
        ?\DateTimeImmutable $acceptedAt,
        array $malleableData = []
    ): void {
        if ($this->state->toString() !== QuoteState::draft()->toString()) {
            throw new \DomainException('Cannot update a quote that is not in draft state.');
        }
        $this->customerId = $customerId;
        $this->totalValue = $totalValue;
        $this->currency = $currency;
        $this->acceptedAt = $acceptedAt;
        $this->malleableData = $malleableData;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function accept(): void
    {
        $this->transitionTo(QuoteState::accepted());
    }

    public function reject(): void
    {
        $this->transitionTo(QuoteState::rejected());
    }

    public function archive(): void
    {
        $this->archivedAt = new \DateTimeImmutable();
    }

    private function transitionTo(QuoteState $newState): void
    {
        if (!$this->state->canTransitionTo($newState)) {
            throw new \DomainException(sprintf(
                'Invalid state transition from %s to %s',
                $this->state->toString(),
                $newState->toString()
            ));
        }

        $this->state = $newState;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
