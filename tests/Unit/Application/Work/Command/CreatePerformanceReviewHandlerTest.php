<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Work\Command;

use Pet\Application\Work\Command\CreatePerformanceReviewCommand;
use Pet\Application\Work\Command\CreatePerformanceReviewHandler;
use Pet\Application\Work\Command\GeneratePersonKpisHandler;
use Pet\Application\Work\Command\GeneratePersonKpisCommand;
use Pet\Domain\Work\Entity\Assignment;
use Pet\Domain\Work\Entity\PerformanceReview;
use Pet\Domain\Work\Repository\AssignmentRepository;
use Pet\Domain\Work\Repository\PerformanceReviewRepository;
use PHPUnit\Framework\TestCase;

class CreatePerformanceReviewHandlerTest extends TestCase
{
    public function testHandleCreatesReviewAndGeneratesKpis(): void
    {
        // Mock dependencies
        $reviewRepository = $this->createMock(PerformanceReviewRepository::class);
        $assignmentRepository = $this->createMock(AssignmentRepository::class);
        $generateKpisHandler = $this->createMock(GeneratePersonKpisHandler::class);

        // Setup Command
        $employeeId = 1;
        $reviewerId = 2;
        $periodStart = '2023-01-01';
        $periodEnd = '2023-03-31';

        $command = new CreatePerformanceReviewCommand(
            $employeeId,
            $reviewerId,
            $periodStart,
            $periodEnd
        );

        // Expect review to be saved
        $reviewRepository->expects($this->once())
            ->method('save')
            ->willReturn(10); // Return ID 10

        // Setup Assignments
        $assignment = $this->createMock(Assignment::class);
        $assignment->method('roleId')->willReturn(5);
        $assignment->method('startDate')->willReturn(new \DateTimeImmutable('2022-01-01'));
        $assignment->method('endDate')->willReturn(null); // Active

        $assignmentRepository->method('findByEmployeeId')
            ->with($employeeId)
            ->willReturn([$assignment]);

        // Expect KPI generation to be triggered
        $generateKpisHandler->expects($this->once())
            ->method('handle')
            ->with($this->callback(function ($kpiCommand) use ($employeeId, $periodStart, $periodEnd) {
                return $kpiCommand instanceof GeneratePersonKpisCommand
                    && $kpiCommand->employeeId() === $employeeId
                    && $kpiCommand->roleId() === 5
                    && $kpiCommand->periodStart()->format('Y-m-d') === $periodStart
                    && $kpiCommand->periodEnd()->format('Y-m-d') === $periodEnd;
            }));

        // Execute Handler
        $handler = new CreatePerformanceReviewHandler(
            $reviewRepository,
            $assignmentRepository,
            $generateKpisHandler
        );

        $result = $handler->handle($command);

        $this->assertEquals(10, $result);
    }
}
