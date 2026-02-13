<?php

declare(strict_types=1);

namespace Pet\Domain\Sla\Entity;

class EscalationRule
{
    private ?int $id;
    private int $thresholdPercent;
    private string $action;

    public function __construct(
        int $thresholdPercent,
        string $action,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->thresholdPercent = $thresholdPercent;
        $this->action = $action;
        
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->thresholdPercent < 1 || $this->thresholdPercent > 100) {
            throw new \DomainException("Threshold percent must be between 1 and 100.");
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function thresholdPercent(): int
    {
        return $this->thresholdPercent;
    }

    public function action(): string
    {
        return $this->action;
    }
}
