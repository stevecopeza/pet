<?php

declare(strict_types=1);

namespace Pet\Application\Work\Cron;

use Pet\Domain\Work\Service\SlaClockCalculator;

class WorkItemPriorityUpdateJob
{
    public function __construct(
        private SlaClockCalculator $calculator
    ) {}

    public function run(): void
    {
        try {
            $count = $this->calculator->recalculateAllActive();
            // Optional logging if needed, or silent success
            // error_log("Updated $count work items priority/SLA.");
        } catch (\Throwable $e) {
            error_log('WorkItem Priority Update Failed: ' . $e->getMessage());
        }
    }
}
