<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Work\Service;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Work\Service\PriorityScoringService;
use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Work\Repository\AssignmentRepository;
use Pet\Domain\Identity\Entity\Employee;
use Pet\Domain\Work\Entity\Assignment;
use DateTimeImmutable;

class PriorityScoringServiceRoleWeightTest extends TestCase
{
    private DateTimeImmutable $now;
    private PriorityScoringService $service;
    private $employeeRepository;
    private $assignmentRepository;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2024-01-01 12:00:00');
        
        $this->employeeRepository = $this->createMock(EmployeeRepository::class);
        $this->assignmentRepository = $this->createMock(AssignmentRepository::class);
        
        $this->service = new PriorityScoringService(
            $this->now,
            $this->employeeRepository,
            $this->assignmentRepository
        );
    }

    private function createWorkItem(
        ?int $requiredRoleId = null,
        ?string $assignedUserId = null
    ): WorkItem {
        $workItem = WorkItem::create(
            'id-123',
            'ticket',
            'src-1',
            'dept-1',
            0.0,
            'active',
            $this->now,
            $requiredRoleId
        );

        if ($assignedUserId) {
            $workItem->assignUser($assignedUserId);
        }

        return $workItem;
    }

    public function testCalculateScoreRoleWeightMatch()
    {
        $requiredRoleId = 10;
        $assignedUserId = "5"; // WP User ID
        $employeeId = 100;

        $workItem = $this->createWorkItem($requiredRoleId, $assignedUserId);

        // Mock Employee
        $employee = $this->createMock(Employee::class);
        $employee->method('id')->willReturn($employeeId);
        $employee->method('wpUserId')->willReturn((int)$assignedUserId);

        $this->employeeRepository->method('findByWpUserId')
            ->with((int)$assignedUserId)
            ->willReturn($employee);

        // Mock Assignment
        $assignment = $this->createMock(Assignment::class);
        $assignment->method('status')->willReturn('active');
        $assignment->method('roleId')->willReturn($requiredRoleId); // Match
        $assignment->method('startDate')->willReturn($this->now->modify('-1 day'));
        $assignment->method('endDate')->willReturn(null);

        $this->assignmentRepository->method('findByEmployeeId')
            ->with($employeeId)
            ->willReturn([$assignment]);

        $score = $this->service->calculate($workItem);
        
        // Expect MAX_ROLE_WEIGHT_COMPONENT (50.0)
        $this->assertEquals(50.0, $score);
    }

    public function testCalculateScoreRoleWeightMismatch()
    {
        $requiredRoleId = 10;
        $assignedUserId = "5";
        $employeeId = 100;

        $workItem = $this->createWorkItem($requiredRoleId, $assignedUserId);

        // Mock Employee
        $employee = $this->createMock(Employee::class);
        $employee->method('id')->willReturn($employeeId);

        $this->employeeRepository->method('findByWpUserId')
            ->with((int)$assignedUserId)
            ->willReturn($employee);

        // Mock Assignment
        $assignment = $this->createMock(Assignment::class);
        $assignment->method('status')->willReturn('active');
        $assignment->method('roleId')->willReturn(99); // Mismatch
        $assignment->method('startDate')->willReturn($this->now->modify('-1 day'));
        $assignment->method('endDate')->willReturn(null);

        $this->assignmentRepository->method('findByEmployeeId')
            ->with($employeeId)
            ->willReturn([$assignment]);

        $score = $this->service->calculate($workItem);
        
        // Expect 0.0
        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreRoleWeightNoRequiredRole()
    {
        $workItem = $this->createWorkItem(null, "5");
        
        $score = $this->service->calculate($workItem);
        
        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreRoleWeightNoAssignedUser()
    {
        $workItem = $this->createWorkItem(10, null);
        
        $score = $this->service->calculate($workItem);
        
        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreRoleWeightExpiredAssignment()
    {
        $requiredRoleId = 10;
        $assignedUserId = "5";
        $employeeId = 100;

        $workItem = $this->createWorkItem($requiredRoleId, $assignedUserId);

        // Mock Employee
        $employee = $this->createMock(Employee::class);
        $employee->method('id')->willReturn($employeeId);

        $this->employeeRepository->method('findByWpUserId')
            ->with((int)$assignedUserId)
            ->willReturn($employee);

        // Mock Assignment (Expired)
        $assignment = $this->createMock(Assignment::class);
        $assignment->method('status')->willReturn('active');
        $assignment->method('roleId')->willReturn($requiredRoleId);
        $assignment->method('startDate')->willReturn($this->now->modify('-10 days'));
        $assignment->method('endDate')->willReturn($this->now->modify('-1 day'));

        $this->assignmentRepository->method('findByEmployeeId')
            ->with($employeeId)
            ->willReturn([$assignment]);

        $score = $this->service->calculate($workItem);
        
        $this->assertEquals(0.0, $score);
    }
}
