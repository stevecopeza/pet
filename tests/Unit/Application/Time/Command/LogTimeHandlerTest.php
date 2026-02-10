<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Time\Command;

use PHPUnit\Framework\TestCase;
use Pet\Application\Time\Command\LogTimeCommand;
use Pet\Application\Time\Command\LogTimeHandler;
use Pet\Domain\Time\Entity\TimeEntry;
use Pet\Domain\Time\Repository\TimeEntryRepository;
use Pet\Domain\Identity\Entity\Employee;
use Pet\Domain\Identity\Repository\EmployeeRepository;

class LogTimeHandlerTest extends TestCase
{
    private $timeEntryRepository;
    private $employeeRepository;
    private $handler;

    protected function setUp(): void
    {
        $this->timeEntryRepository = $this->createMock(TimeEntryRepository::class);
        $this->employeeRepository = $this->createMock(EmployeeRepository::class);
        $this->handler = new LogTimeHandler(
            $this->timeEntryRepository,
            $this->employeeRepository
        );
    }

    public function testHandleLogsTimeSuccessfully()
    {
        $employeeId = 1;
        $taskId = 10;
        $start = new \DateTimeImmutable('2023-01-01 10:00:00');
        $end = new \DateTimeImmutable('2023-01-01 12:00:00');
        
        $command = new LogTimeCommand(
            $employeeId,
            $taskId,
            $start,
            $end,
            true,
            'Worked on feature X'
        );
        
        $employee = $this->createMock(Employee::class);
        $this->employeeRepository->method('findById')
            ->with($employeeId)
            ->willReturn($employee);

        $this->timeEntryRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (TimeEntry $entry) use ($employeeId, $taskId) {
                return $entry->employeeId() === $employeeId
                    && $entry->taskId() === $taskId
                    && $entry->durationMinutes() === 120;
            }));

        $this->handler->handle($command);
    }

    public function testHandleThrowsExceptionIfEmployeeNotFound()
    {
        $command = new LogTimeCommand(
            999,
            10,
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            true,
            'Test'
        );

        $this->employeeRepository->method('findById')
            ->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->handler->handle($command);
    }
}
