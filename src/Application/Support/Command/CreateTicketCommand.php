<?php

declare(strict_types=1);

namespace Pet\Application\Support\Command;

class CreateTicketCommand
{
    private int $customerId;
    private string $subject;
    private string $description;
    private string $priority;

    public function __construct(
        int $customerId,
        string $subject,
        string $description,
        string $priority = 'medium'
    ) {
        $this->customerId = $customerId;
        $this->subject = $subject;
        $this->description = $description;
        $this->priority = $priority;
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

    public function priority(): string
    {
        return $this->priority;
    }
}
