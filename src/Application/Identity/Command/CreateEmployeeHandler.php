<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

use Pet\Domain\Identity\Entity\Employee;
use Pet\Domain\Identity\Repository\EmployeeRepository;

class CreateEmployeeHandler
{
    private EmployeeRepository $employeeRepository;

    public function __construct(EmployeeRepository $employeeRepository)
    {
        $this->employeeRepository = $employeeRepository;
    }

    public function handle(CreateEmployeeCommand $command): void
    {
        $employee = new Employee(
            $command->wpUserId(),
            $command->firstName(),
            $command->lastName(),
            $command->email()
        );

        $this->employeeRepository->save($employee);
    }
}
