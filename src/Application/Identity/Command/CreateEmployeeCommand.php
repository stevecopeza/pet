<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

class CreateEmployeeCommand
{
    private int $wpUserId;
    private string $firstName;
    private string $lastName;
    private string $email;

    public function __construct(int $wpUserId, string $firstName, string $lastName, string $email)
    {
        $this->wpUserId = $wpUserId;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
    }

    public function wpUserId(): int
    {
        return $this->wpUserId;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function email(): string
    {
        return $this->email;
    }
}
