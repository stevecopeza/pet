<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Work\Service;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Work\Service\CapacityCalendar;
use Pet\Domain\Calendar\Repository\CalendarRepository;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Calendar\Service\BusinessTimeCalculator;
use Pet\Domain\Calendar\Entity\Calendar;
use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Identity\Entity\Employee;
use DateTimeImmutable;

class CapacityCalendarTest extends TestCase
{
    private $calendarRepository;
    private $workItemRepository;
    private $employeeRepository;
    private $timeCalculator;
    private $capacityCalendar;

    protected function setUp(): void
    {
        $this->calendarRepository = $this->createMock(CalendarRepository::class);
        $this->workItemRepository = $this->createMock(WorkItemRepository::class);
        $this->employeeRepository = $this->createMock(EmployeeRepository::class);
        $this->timeCalculator = $this->createMock(BusinessTimeCalculator::class);

        $this->capacityCalendar = new CapacityCalendar(
            $this->calendarRepository,
            $this->workItemRepository,
            $this->employeeRepository,
            $this->timeCalculator
        );
    }

    public function testGetUserUtilizationCalculatesCorrectly()
    {
        // 1. Arrange Calendar and Time
        $start = new DateTimeImmutable('2024-03-01 09:00:00');
        $end = new DateTimeImmutable('2024-03-01 17:00:00'); // 8 hours

        $calendar = $this->createMock(Calendar::class);
        $snapshot = ['mock' => 'snapshot']; // Calendar::createSnapshot returns array, not object
        $calendar->method('createSnapshot')->willReturn($snapshot);

        // Fallback to default
        $this->calendarRepository->method('findDefault')->willReturn($calendar);
        $this->employeeRepository->method('findByWpUserId')->willReturn(null);

        // Available time: 8 hours = 480 minutes
        $this->timeCalculator->expects($this->exactly(2)) // Once for available, once for item overlap
            ->method('calculateBusinessMinutes')
            ->willReturnOnConsecutiveCalls(480, 480);

        // 2. Arrange Work Items
        $item = $this->createMock(WorkItem::class);
        $item->method('getStatus')->willReturn('active');
        $item->method('getScheduledStartUtc')->willReturn($start);
        $item->method('getScheduledDueUtc')->willReturn($end);
        $item->method('getCapacityAllocationPercent')->willReturn(50.0); // 50% allocation

        $this->workItemRepository->method('findByAssignedUser')
            ->with('user-1')
            ->willReturn([$item]);

        // 3. Act
        $utilization = $this->capacityCalendar->getUserUtilization('user-1', $start, $end);

        // 4. Assert
        // Available: 480 mins
        // Assigned: 480 mins * 50% = 240 mins
        // Utilization: (240 / 480) * 100 = 50.0%
        $this->assertEquals(50.0, $utilization);
    }

    public function testGetUserUtilizationWithMultipleItems()
    {
        // 1. Arrange Calendar and Time
        $start = new DateTimeImmutable('2024-03-01 09:00:00');
        $end = new DateTimeImmutable('2024-03-01 17:00:00'); // 8 hours

        $calendar = $this->createMock(Calendar::class);
        $snapshot = ['mock' => 'snapshot'];
        $calendar->method('createSnapshot')->willReturn($snapshot);

        // Fallback to default
        $this->calendarRepository->method('findDefault')->willReturn($calendar);
        $this->employeeRepository->method('findByWpUserId')->willReturn(null);

        // Available time: 480 minutes
        // Item 1 overlap: 480 minutes
        // Item 2 overlap: 480 minutes
        $this->timeCalculator->method('calculateBusinessMinutes')
            ->willReturn(480);

        // 2. Arrange Work Items
        $item1 = $this->createMock(WorkItem::class);
        $item1->method('getStatus')->willReturn('active');
        $item1->method('getScheduledStartUtc')->willReturn($start);
        $item1->method('getScheduledDueUtc')->willReturn($end);
        $item1->method('getCapacityAllocationPercent')->willReturn(50.0);

        $item2 = $this->createMock(WorkItem::class);
        $item2->method('getStatus')->willReturn('active');
        $item2->method('getScheduledStartUtc')->willReturn($start);
        $item2->method('getScheduledDueUtc')->willReturn($end);
        $item2->method('getCapacityAllocationPercent')->willReturn(25.0);

        $this->workItemRepository->method('findByAssignedUser')
            ->with('user-1')
            ->willReturn([$item1, $item2]);

        // 3. Act
        $utilization = $this->capacityCalendar->getUserUtilization('user-1', $start, $end);

        // 4. Assert
        // Available: 480 mins
        // Assigned: (480 * 0.5) + (480 * 0.25) = 240 + 120 = 360 mins
        // Utilization: (360 / 480) * 100 = 75.0%
        $this->assertEquals(75.0, $utilization);
    }

    public function testGetUserUtilizationReturnsZeroIfNoCalendar()
    {
        $this->calendarRepository->method('findDefault')->willReturn(null);
        $this->employeeRepository->method('findByWpUserId')->willReturn(null);
        
        $utilization = $this->capacityCalendar->getUserUtilization(
            'user-1', 
            new DateTimeImmutable(), 
            new DateTimeImmutable()
        );

        $this->assertEquals(0.0, $utilization);
    }

    public function testGetUserUtilizationUsesUserSpecificCalendar()
    {
        // 1. Arrange Calendar and Time
        $start = new DateTimeImmutable('2024-03-01 09:00:00');
        $end = new DateTimeImmutable('2024-03-01 17:00:00'); // 8 hours

        // Specific Calendar
        $specificCalendar = $this->createMock(Calendar::class);
        $specificSnapshot = ['mock' => 'specific_snapshot'];
        $specificCalendar->method('createSnapshot')->willReturn($specificSnapshot);

        // Default Calendar (should not be used)
        $defaultCalendar = $this->createMock(Calendar::class);
        $this->calendarRepository->method('findDefault')->willReturn($defaultCalendar);

        // Employee Setup
        $employee = $this->createMock(Employee::class);
        $employee->method('calendarId')->willReturn(123);
        $this->employeeRepository->method('findByWpUserId')->with(999)->willReturn($employee);

        // Calendar Repo finds specific calendar
        $this->calendarRepository->method('findById')->with(123)->willReturn($specificCalendar);

        // Time Calculator uses specific snapshot
        $this->timeCalculator->expects($this->exactly(2))
            ->method('calculateBusinessMinutes')
            ->with($this->anything(), $this->anything(), $specificSnapshot)
            ->willReturnOnConsecutiveCalls(480, 480);

        // 2. Arrange Work Items
        $item = $this->createMock(WorkItem::class);
        $item->method('getStatus')->willReturn('active');
        $item->method('getScheduledStartUtc')->willReturn($start);
        $item->method('getScheduledDueUtc')->willReturn($end);
        $item->method('getCapacityAllocationPercent')->willReturn(50.0);

        $this->workItemRepository->method('findByAssignedUser')
            ->with('999')
            ->willReturn([$item]);

        // 3. Act
        // Use numeric string ID '999' which triggers user-specific lookup
        $utilization = $this->capacityCalendar->getUserUtilization('999', $start, $end);

        // 4. Assert
        $this->assertEquals(50.0, $utilization);
    }
}
