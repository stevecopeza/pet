<?php

declare(strict_types=1);

namespace Pet\Application\Time\Command;

class LogTimeCommand
{
    private int $employeeId;
    private int $taskId;
    private \DateTimeImmutable $start;
    private \DateTimeImmutable $end;
    private bool $isBillable;
    private string $description;
    private array $malleableData;

    public function __construct(
        int $employeeId,
        int $taskId,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        bool $isBillable,
        string $description,
        array $malleableData = []
    ) {
        $this->employeeId = $employeeId;
        $this->taskId = $taskId;
        $this->start = $start;
        $this->end = $end;
        $this->isBillable = $isBillable;
        $this->description = $description;
        $this->malleableData = $malleableData;
    }

    public function employeeId(): int
    {
        return $this->employeeId;
    }

    public function taskId(): int
    {
        return $this->taskId;
    }

    public function start(): \DateTimeImmutable
    {
        return $this->start;
    }

    public function end(): \DateTimeImmutable
    {
        return $this->end;
    }

    public function isBillable(): bool
    {
        return $this->isBillable;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function malleableData(): array
    {
        return $this->malleableData;
    }
}
