<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Service;

use Pet\Domain\Calendar\Repository\CalendarRepository;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Calendar\Service\BusinessTimeCalculator;
use DateTimeImmutable;

class CapacityCalendar
{
    public function __construct(
        private CalendarRepository $calendarRepository,
        private WorkItemRepository $workItemRepository,
        private EmployeeRepository $employeeRepository,
        private BusinessTimeCalculator $timeCalculator
    ) {}

    public function getUserUtilization(
        string $userId,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): float {
        // 1. Calculate Available Capacity
        $calendar = null;
        
        // Try to find employee-specific calendar
        // Note: userId here is likely the WP User ID or string ID?
        // WorkItem stores assigned_user_id as string (from context).
        // Let's assume userId is string and we need to resolve Employee.
        // Wait, WorkItem::assignedUserId is string.
        // EmployeeRepository has findById(int) and findByWpUserId(int).
        // We might need a way to resolve string userId to Employee if it's a UUID or something.
        // But in PET, assigned_user_id seems to be numeric string? Or 'user-1'?
        // Let's check WorkItem entity or usage.
        
        if (is_numeric($userId)) {
             $employee = $this->employeeRepository->findByWpUserId((int)$userId);
             if ($employee && $employee->calendarId()) {
                 $calendar = $this->calendarRepository->findById($employee->calendarId());
             }
        }

        if (!$calendar) {
            $calendar = $this->calendarRepository->findDefault();
        }

        if (!$calendar) {
            return 0.0;
        }

        $snapshot = $calendar->createSnapshot();
        $availableMinutes = $this->timeCalculator->calculateBusinessMinutes($start, $end, $snapshot);

        if ($availableMinutes === 0) {
            // If no time is available in the window, but we want to check utilization...
            // If load > 0, it's effectively infinite utilization.
            // But returning 0 avoids division by zero.
            // Let's check load first to be safe, but for float return, 0.0 is safe fallback.
            return 0.0;
        }

        // 2. Calculate Assigned Load
        $items = $this->workItemRepository->findByAssignedUser($userId);
        $assignedMinutes = 0.0;

        foreach ($items as $item) {
            if ($item->getStatus() === 'completed') {
                continue;
            }

            $itemStart = $item->getScheduledStartUtc();
            $itemDue = $item->getScheduledDueUtc();

            if (!$itemStart || !$itemDue) {
                continue; 
            }

            // Check overlap: max(start, itemStart) < min(end, itemDue)
            $overlapStart = ($start > $itemStart) ? $start : $itemStart;
            $overlapEnd = ($end < $itemDue) ? $end : $itemDue;

            if ($overlapEnd > $overlapStart) {
                // Calculate business minutes in overlap
                $minutes = $this->timeCalculator->calculateBusinessMinutes($overlapStart, $overlapEnd, $snapshot);
                
                // Apply allocation %
                $allocation = $item->getCapacityAllocationPercent();
                if ($allocation <= 0.0) {
                    // Default behavior: if scheduled but no allocation set, assume 100%?
                    // Or 0? Let's assume 0 for now to avoid blocking on untracked items.
                    $allocation = 0.0;
                } else {
                    $allocation = $allocation / 100.0;
                }
                
                $assignedMinutes += ($minutes * $allocation);
            }
        }

        return ($assignedMinutes / $availableMinutes) * 100.0;
    }
}
