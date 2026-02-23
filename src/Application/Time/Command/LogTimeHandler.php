<?php

declare(strict_types=1);

namespace Pet\Application\Time\Command;

use Pet\Domain\Time\Entity\TimeEntry;
use Pet\Domain\Time\Repository\TimeEntryRepository;
use Pet\Domain\Identity\Repository\EmployeeRepository;

class LogTimeHandler
{
    private TimeEntryRepository $timeEntryRepository;
    private EmployeeRepository $employeeRepository;
    
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
            $command->ticketId(),
            $command->start(),
            $command->end(),
            $command->isBillable(),
            $command->description(),
            'draft',
            null,
            $command->malleableData()
        );

        $this->timeEntryRepository->save($timeEntry);
    }
}
