<?php

declare(strict_types=1);

namespace Pet\Domain\Support\Entity;

class Ticket
{
    private ?int $id;
    private int $customerId;
    private string $subject;
    private string $description;
    private string $status;
    private string $priority;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $resolvedAt;

    public function __construct(
        int $customerId,
        string $subject,
        string $description,
        string $status = 'new',
        string $priority = 'medium',
        ?int $id = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $resolvedAt = null
    ) {
        $this->id = $id;
        $this->customerId = $customerId;
        $this->subject = $subject;
        $this->description = $description;
        $this->status = $status;
        $this->priority = $priority;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->resolvedAt = $resolvedAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function priority(): string
    {
        return $this->priority;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function resolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }
}
