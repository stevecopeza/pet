<?php

declare(strict_types=1);

namespace Pet\Domain\Finance\Entity;

final class BillingExport
{
    private int $id;
    private string $uuid;
    private int $customerId;
    private \DateTimeImmutable $periodStart;
    private \DateTimeImmutable $periodEnd;
    private string $status;
    private int $createdByEmployeeId;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        int $id,
        string $uuid,
        int $customerId,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
        string $status,
        int $createdByEmployeeId,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->uuid = $uuid;
        $this->customerId = $customerId;
        $this->periodStart = $periodStart;
        $this->periodEnd = $periodEnd;
        $this->status = $status;
        $this->createdByEmployeeId = $createdByEmployeeId;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function draft(
        string $uuid,
        int $customerId,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
        int $createdByEmployeeId
    ): self {
        $now = new \DateTimeImmutable();
        return new self(
            0,
            $uuid,
            $customerId,
            $periodStart,
            $periodEnd,
            'draft',
            $createdByEmployeeId,
            $now,
            $now
        );
    }

    public function id(): int { return $this->id; }
    public function setId(int $id): void { $this->id = $id; }
    public function uuid(): string { return $this->uuid; }
    public function customerId(): int { return $this->customerId; }
    public function periodStart(): \DateTimeImmutable { return $this->periodStart; }
    public function periodEnd(): \DateTimeImmutable { return $this->periodEnd; }
    public function status(): string { return $this->status; }
    public function setStatus(string $status): void { $this->status = $status; }
    public function createdByEmployeeId(): int { return $this->createdByEmployeeId; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
