<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

class CreateCustomerCommand
{
    private string $name;
    private string $contactEmail;

    public function __construct(string $name, string $contactEmail)
    {
        $this->name = $name;
        $this->contactEmail = $contactEmail;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function contactEmail(): string
    {
        return $this->contactEmail;
    }
}
