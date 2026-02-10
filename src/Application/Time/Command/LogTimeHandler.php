<?php

declare(strict_types=1);

namespace Pet\Application\Time\Command;

use Pet\Domain\Time\Entity\TimeEntry;
use Pet\Domain\Time\Repository\TimeEntryRepository;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Delivery\Repository\ProjectRepository; // Task is in Project agg

class LogTimeHandler
{
    private TimeEntryRepository $timeEntryRepository;
    private EmployeeRepository $employeeRepository;
    
    // Ideally we should check if task exists. 
    // Assuming we have a way to check task existence.
    // Since Task is part of Project aggregate, we might need ProjectRepository or a TaskReadModel.
    // For now, let's assume valid taskId or minimal check.
    // But wait, we don't have a direct TaskRepository. Tasks are in Projects.
    // We'll skip complex task validation for this MVP step or check if we can query it.
    
    public function __construct(
        TimeEntryRepository $timeEntryRepository,
        EmployeeRepository $employeeRepository
    ) {
        $this->timeEntryRepository = $timeEntryRepository;
        $this->employeeRepository = $employeeRepository;
    }

    public function handle(LogTimeCommand $command): void
    {
        $employee = $this->employeeRepository->findById($command->employeeId());
        if (!$employee) {
            throw new \DomainException("Employee not found: {$command->employeeId()}");
        }

        $timeEntry = new TimeEntry(
            $command->employeeId(),
            $command->taskId(),
            $command->start(),
            $command->end(),
            $command->isBillable(),
            $command->description()
        );

        $this->timeEntryRepository->save($timeEntry);
    }
}
