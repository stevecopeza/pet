<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Work\Service;

use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Work\Service\PriorityScoringService;
use Pet\Domain\Work\Service\SlaClockCalculator;
use Pet\Domain\Advisory\Repository\AdvisorySignalRepository;
use Pet\Domain\Advisory\Entity\AdvisorySignal;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

class SlaClockCalculatorTest extends TestCase
{
    private $workItemRepository;
    private $scoringService;
    private $signalRepository;
    private $calculator;

    protected function setUp(): void
    {
        $this->workItemRepository = $this->createMock(WorkItemRepository::class);
        $this->scoringService = $this->createMock(PriorityScoringService::class);
        $this->signalRepository = $this->createMock(AdvisorySignalRepository::class);

        $this->calculator = new SlaClockCalculator(
            $this->workItemRepository,
            $this->scoringService,
            $this->signalRepository
        );
    }

    public function testRecalculateAllActiveUpdatesScoreAndSaves()
    {
        $workItem = $this->createMock(WorkItem::class);
        $workItem->method('getId')->willReturn('item-1');
        $workItem->method('getDepartmentId')->willReturn('dept-1');
        $workItem->method('getStatus')->willReturn('active');
        // No due date
        $workItem->method('getScheduledDueUtc')->willReturn(null);

        $this->workItemRepository->method('findActive')->willReturn([$workItem]);

        $this->scoringService->expects($this->once())
            ->method('calculate')
            ->with($workItem)
            ->willReturn(50.0);

        $workItem->expects($this->once())->method('updatePriorityScore')->with(50.0);
        $this->workItemRepository->expects($this->once())->method('save')->with($workItem);

        $count = $this->calculator->recalculateAllActive();
        $this->assertEquals(1, $count);
    }

    public function testSlaBreachGeneratesSignal()
    {
        $workItem = $this->createMock(WorkItem::class);
        $workItem->method('getId')->willReturn('item-1');
        $workItem->method('getDepartmentId')->willReturn('dept-1');
        $workItem->method('getStatus')->willReturn('active');
        $workItem->method('getSlaSnapshotId')->willReturn('sla-1');
        
        // Due 1 hour ago
        $due = (new DateTimeImmutable())->modify('-60 minutes');
        $workItem->method('getScheduledDueUtc')->willReturn($due);

        $this->workItemRepository->method('findActive')->willReturn([$workItem]);

        // Expect signal creation
        $this->signalRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (AdvisorySignal $signal) {
                return $signal->getSignalType() === AdvisorySignal::TYPE_SLA_RISK &&
                       $signal->getSeverity() === AdvisorySignal::SEVERITY_CRITICAL;
            }));

        $this->calculator->recalculateAllActive();
    }

    public function testCapacityBottleneckGeneratesSignal()
    {
        // Create 11 items for dept-1 to trigger bottleneck
        $items = [];
        for ($i = 0; $i < 11; $i++) {
            $item = $this->createMock(WorkItem::class);
            $item->method('getId')->willReturn('item-' . $i);
            $item->method('getDepartmentId')->willReturn('dept-1');
            $item->method('getStatus')->willReturn('waiting'); // Must be waiting
            $items[] = $item;
        }

        $this->workItemRepository->method('findActive')->willReturn($items);

        // Expect signals for all 11 items (since all are waiting and threshold is crossed)
        // Or maybe just for the ones processed? The loop processes all.
        // The bottleneck check is inside the loop.
        // deptCounts will be 11.
        // For each item, if status is waiting (all are), signal is created.
        
        $this->signalRepository->expects($this->atLeast(11))
            ->method('save');

        $this->calculator->recalculateAllActive();
    }
}
