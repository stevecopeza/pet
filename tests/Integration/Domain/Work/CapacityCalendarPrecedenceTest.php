<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Domain\Work;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Work\Service\CapacityCalendar;
use Pet\Domain\Calendar\Service\BusinessTimeCalculator;
use Pet\Domain\Calendar\Entity\Calendar;
use Pet\Domain\Calendar\Entity\WorkingWindow;
use Pet\Domain\Identity\Entity\Employee;

class CapacityCalendarPrecedenceTest extends TestCase
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

        // Create a simple 8-hour/day calendar (Mon-Fri 09:00-17:00)
        $windows = [];
        foreach (['monday','tuesday','wednesday','thursday','friday'] as $dow) {
            $windows[] = (new WorkingWindow($dow, '09:00', '17:00'))->toArray();
        }
        $this->calendar = new Calendar('Default', 'UTC', array_map(fn($w) => new WorkingWindow($w['day_of_week'], $w['start_time'], $w['end_time']), $windows), []);

        $this->calendarRepo->method('findDefault')->willReturn($this->calendar);
        $this->calendarRepo->method('findById')->willReturn($this->calendar);

        // No scheduled items for simplicity
        $this->workItemRepo->method('findByAssignedUser')->willReturn([]);

        // Employee with calendarId null (use default), id=1, wpUserId=100
        $employee = new Employee(100, 'John', 'Doe', 'john@example.com', 1, 'active', null, null, null);
        $this->employeeRepo->method('findById')->willReturn($employee);
        $this->employeeRepo->method('findByWpUserId')->willReturn($employee);
    }

    public function testDefaultCapacityNoLeaveNoOverride(): void
    {
        $this->leaveRepo->method('isApprovedOnDate')->willReturn(false);
        $this->overrideRepo->method('findForDate')->willReturn(null);

        $service = new CapacityCalendar(
            $this->calendarRepo,
            $this->workItemRepo,
            $this->employeeRepo,
            $this->btc,
            $this->leaveRepo,
            $this->overrideRepo
        );

        // Pick a Monday
        $start = new \DateTimeImmutable('2025-01-06'); // Monday
        $end = new \DateTimeImmutable('2025-01-06');
        $daily = $service->getUserDailyUtilization(1, $start, $end);
        $this->assertCount(1, $daily);
        $this->assertEquals(480.0, $daily[0]['effective_capacity_minutes']);
        $this->assertEquals(0.0, $daily[0]['scheduled_minutes']);
        $this->assertEquals(0.0, $daily[0]['utilization_pct']);
    }

    public function testApprovedLeaveSetsZeroCapacity(): void
    {
        $this->leaveRepo->method('isApprovedOnDate')->willReturn(true);
        $this->overrideRepo->method('findForDate')->willReturn(null);

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
        $this->assertEquals(0.0, $daily[0]['effective_capacity_minutes']);
        $this->assertEquals(0.0, $daily[0]['scheduled_minutes']);
        $this->assertEquals(0.0, $daily[0]['utilization_pct']);
    }

    public function testOverrideScalesCapacity(): void
    {
        $this->leaveRepo->method('isApprovedOnDate')->willReturn(false);
        $override = new \Pet\Domain\Work\Entity\CapacityOverride(
            1,
            1,
            new \DateTimeImmutable('2025-01-06'),
            50,
            null,
            new \DateTimeImmutable('2025-01-01')
        );
        $this->overrideRepo->method('findForDate')->willReturn($override);

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
        $this->assertEquals(240.0, $daily[0]['effective_capacity_minutes']);
        $this->assertEquals(0.0, $daily[0]['scheduled_minutes']);
        $this->assertEquals(0.0, $daily[0]['utilization_pct']);
    }
}
