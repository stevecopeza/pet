<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

class CreateQuoteCommand
{
    private int $customerId;

    public function __construct(int $customerId)
    {
        $this->customerId = $customerId;
    }

    public function customerId(): int
    {
        return $this->customerId;
    }
}
