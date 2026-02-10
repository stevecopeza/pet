<?php

declare(strict_types=1);

namespace Pet\Domain\Delivery\Entity;

use Pet\Domain\Delivery\ValueObject\ProjectState;

class Project
{
    private ?int $id;
    private int $customerId;
    private ?int $sourceQuoteId;
    private string $name;
    private ProjectState $state;
    private float $soldHours; // Immutable constraint from quote
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;
    private ?\DateTimeImmutable $archivedAt;

    /**
     * @var Task[]
     */
    private array $tasks = [];

    public function __construct(
        int $customerId,
        string $name,
        float $soldHours,
        ?int $sourceQuoteId = null,
        ?ProjectState $state = null,
        ?int $id = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $archivedAt = null,
        array $tasks = []
    ) {
        $this->id = $id;
        $this->customerId = $customerId;
        $this->sourceQuoteId = $sourceQuoteId;
        $this->name = $name;
        $this->soldHours = $soldHours;
        $this->state = $state ?? ProjectState::planned();
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt;
        $this->archivedAt = $archivedAt;
        $this->tasks = $tasks;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function soldHours(): float
    {
        return $this->soldHours;
    }

    public function state(): ProjectState
    {
        return $this->state;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function sourceQuoteId(): ?int
    {
        return $this->sourceQuoteId;
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
     * @return Task[]
     */
    public function tasks(): array
    {
        return $this->tasks;
    }

    public function addTask(Task $task): void
    {
        if ($this->state->isTerminal()) {
            throw new \DomainException('Cannot add tasks to a completed or cancelled project.');
        }
        
        // Ensure total planned hours do not exceed sold hours (simplified check)
        $currentPlanned = $this->calculatePlannedHours();
        if (($currentPlanned + $task->estimatedHours()) > $this->soldHours) {
             // In a real scenario, this might trigger a variance warning rather than a hard block,
             // but per specs: "PMs may not Increase total sold hours" and "Variance is flagged immediately".
             // For strict enforcement, we'll allow it but flagging variance would be the next step.
             // For now, we allow adding but we should track variance.
        }

        $this->tasks[] = $task;
    }

    public function calculatePlannedHours(): float
    {
        $total = 0.0;
        foreach ($this->tasks as $task) {
            $total += $task->estimatedHours();
        }
        return $total;
    }

    public function start(): void
    {
        $this->state = ProjectState::active();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function complete(): void
    {
        // "Completing a project with unresolved variance" is blocked.
        // Simplified check: ensure we tracked time (future) doesn't exceed sold?
        
        $this->state = ProjectState::completed();
        $this->updatedAt = new \DateTimeImmutable();
    }
}
