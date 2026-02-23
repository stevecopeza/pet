<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Domain\Work;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Work\Service\CapacityCalendar;
use Pet\Domain\Calendar\Service\BusinessTimeCalculator;
use Pet\Domain\Calendar\Entity\Calendar;
use Pet\Domain\Calendar\Entity\WorkingWindow;
use Pet\Domain\Identity\Entity\Employee;
use Pet\Domain\Work\Entity\WorkItem;

class CapacityCalendarUtilizationWithWorkItemsTest extends TestCase
{
    private $calendarRepo;
    private $workItemRepo;
    private $employeeRepo;
    private $leaveRepo;
    private $overrideRepo;
    private BusinessTimeCalculator $btc;
    private Calendar $calendar;

    protected function setUp(): void
    {
        $this->calendarRepo = $this->createMock(\Pet\Domain\Calendar\Repository\CalendarRepository::class);
        $this->workItemRepo = $this->createMock(\Pet\Domain\Work\Repository\WorkItemRepository::class);
        $this->employeeRepo = $this->createMock(\Pet\Domain\Identity\Repository\EmployeeRepository::class);
        $this->leaveRepo = $this->createMock(\Pet\Domain\Work\Repository\LeaveRequestRepository::class);
        $this->overrideRepo = $this->createMock(\Pet\Domain\Work\Repository\CapacityOverrideRepository::class);
        $this->btc = new BusinessTimeCalculator();

        // 8-hour/day calendar (Mon-Fri 09:00–17:00)
        $windows = [];
        foreach (['monday','tuesday','wednesday','thursday','friday'] as $dow) {
            $windows[] = new WorkingWindow($dow, '09:00', '17:00');
        }
        $this->calendar = new Calendar('Default', 'UTC', $windows, []);
        $this->calendarRepo->method('findDefault')->willReturn($this->calendar);
        $this->calendarRepo->method('findById')->willReturn($this->calendar);

        // Employee id=1, wpUserId=100
        $employee = new Employee(100, 'John', 'Doe', 'john@example.com', 1, 'active', null, null, null);
        $this->employeeRepo->method('findById')->willReturn($employee);
        $this->employeeRepo->method('findByWpUserId')->willReturn($employee);

        // No leave or overrides
        $this->leaveRepo->method('isApprovedOnDate')->willReturn(false);
        $this->overrideRepo->method('findForDate')->willReturn(null);
    }

    public function testUtilizationWithSingleWorkItemOverlapAndAllocation(): void
    {
        // Work item scheduled 10:00–12:00 with 50% allocation
        $wi = WorkItem::create(
            'wi-1', 'ticket', 'T-1', 'support', 1.0, 'active', new \DateTimeImmutable('2025-01-06 00:00:00')
        );
        $wi->assignUser('100');
        $wi->updateScheduling(
            new \DateTimeImmutable('2025-01-06 10:00:00'),
            new \DateTimeImmutable('2025-01-06 12:00:00')
        );
        $wi->updateCapacityAllocation(50.0);
        $this->workItemRepo->method('findByAssignedUser')->willReturn([$wi]);

        $service = new CapacityCalendar(
            $this->calendarRepo,
            $this->workItemRepo,
            $this->employeeRepo,
            $this->btc,
            $this->leaveRepo,
            $this->overrideRepo
        );

        $start = new \DateTimeImmutable('2025-01-06'); // Monday
        $end = new \DateTimeImmutable('2025-01-06');
        $daily = $service->getUserDailyUtilization(1, $start, $end);
        $this->assertCount(1, $daily);
        // Effective capacity = 480 minutes
        $this->assertEquals(480.0, $daily[0]['effective_capacity_minutes']);
        // Scheduled business minutes in overlap = 120 * 0.5 = 60
        $this->assertEquals(60.0, $daily[0]['scheduled_minutes']);
        // Utilization = 60 / 480 * 100 = 12.5%
        $this->assertEquals(12.5, $daily[0]['utilization_pct']);
    }

    public function testUtilizationWithMultipleOverlappingWorkItemsMixedAllocations(): void
    {
        $wi1 = WorkItem::create(
            'wi-1', 'ticket', 'T-1', 'support', 1.0, 'active', new \DateTimeImmutable('2025-01-06 00:00:00')
        );
        $wi1->assignUser('100');
        $wi1->updateScheduling(
            new \DateTimeImmutable('2025-01-06 09:30:00'),
            new \DateTimeImmutable('2025-01-06 11:00:00')
        );
        $wi1->updateCapacityAllocation(50.0);

        $wi2 = WorkItem::create(
            'wi-2', 'ticket', 'T-2', 'support', 1.0, 'active', new \DateTimeImmutable('2025-01-06 00:00:00')
        );
        $wi2->assignUser('100');
        $wi2->updateScheduling(
            new \DateTimeImmutable('2025-01-06 10:00:00'),
            new \DateTimeImmutable('2025-01-06 11:30:00')
        );
        $wi2->updateCapacityAllocation(25.0);

        $this->workItemRepo->method('findByAssignedUser')->willReturn([$wi1, $wi2]);

        $service = new CapacityCalendar(
            $this->calendarRepo,
            $this->workItemRepo,
            $this->employeeRepo,
            $this->btc,
            $this->leaveRepo,
            $this->overrideRepo
        );

        $start = new \DateTimeImmutable('2025-01-06');
        $end = new \DateTimeImmutable('2025-01-06');
        $daily = $service->getUserDailyUtilization(1, $start, $end);
        $this->assertCount(1, $daily);
        $this->assertEquals(480.0, $daily[0]['effective_capacity_minutes']);
        // 09:30–11:00 = 90 min * 0.5 = 45; 10:00–11:30 = 90 min * 0.25 = 22.5
        // Total scheduled = 67.5 minutes
        $this->assertEquals(67.5, $daily[0]['scheduled_minutes']);
        $this->assertEquals(14.06, $daily[0]['utilization_pct']);
    }
}
