<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Advisory\Service;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Advisory\Service\AdvisoryGenerator;
use Pet\Domain\Advisory\Repository\AdvisorySignalRepository;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Work\Service\CapacityCalendar;
use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Advisory\Entity\AdvisorySignal;
use DateTimeImmutable;

class AdvisoryGeneratorTest extends TestCase
{
    private $signalRepository;
    private $workItemRepository;
    private $capacityCalendar;
    private $generator;

    protected function setUp(): void
    {
        $this->signalRepository = $this->createMock(AdvisorySignalRepository::class);
        $this->workItemRepository = $this->createMock(WorkItemRepository::class);
        $this->capacityCalendar = $this->createMock(CapacityCalendar::class);

        $this->generator = new AdvisoryGenerator(
            $this->signalRepository,
            $this->workItemRepository,
            $this->capacityCalendar
        );
    }

    public function testGenerateForUserDetectsContextSwitching()
    {
        // Arrange: 4 active items
        $items = [];
        for ($i = 0; $i < 4; $i++) {
            $item = $this->createMock(WorkItem::class);
            $item->method('getStatus')->willReturn('active');
            $item->method('getId')->willReturn('item-' . $i);
            $items[] = $item;
        }

        $this->workItemRepository->method('findByAssignedUser')->willReturn($items);
        $this->capacityCalendar->method('getUserUtilization')->willReturn(50.0);

        // Expect: 4 Context Switching signals (one per item)
        $this->signalRepository->expects($this->exactly(4))
            ->method('save')
            ->with($this->callback(function (AdvisorySignal $signal) {
                return $signal->getSignalType() === AdvisorySignal::TYPE_CONTEXT_SWITCHING;
            }));

        // Act
        $this->generator->generateForUser('user-1');
    }

    public function testGenerateForUserDetectsCapacityBottleneck()
    {
        // Arrange: 1 active item, 120% utilization
        $item = $this->createMock(WorkItem::class);
        $item->method('getStatus')->willReturn('active');
        $item->method('getId')->willReturn('item-1');

        $this->workItemRepository->method('findByAssignedUser')->willReturn([$item]);
        $this->capacityCalendar->method('getUserUtilization')->willReturn(120.0);

        // Expect: 1 Capacity Bottleneck signal
        $this->signalRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (AdvisorySignal $signal) {
                return $signal->getSignalType() === AdvisorySignal::TYPE_CAPACITY_BOTTLENECK;
            }));

        // Act
        $this->generator->generateForUser('user-1');
    }

    public function testGenerateForUserDetectsSlaRisk()
    {
        // Arrange: 1 active item, 200 mins SLA remaining (< 240 threshold)
        $item = $this->createMock(WorkItem::class);
        $item->method('getStatus')->willReturn('active');
        $item->method('getId')->willReturn('item-1');
        $item->method('getSlaTimeRemainingMinutes')->willReturn(200);

        $this->workItemRepository->method('findByAssignedUser')->willReturn([$item]);
        $this->capacityCalendar->method('getUserUtilization')->willReturn(50.0);

        // Expect: 1 SLA Risk signal
        $this->signalRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (AdvisorySignal $signal) {
                return $signal->getSignalType() === AdvisorySignal::TYPE_SLA_RISK;
            }));

        // Act
        $this->generator->generateForUser('user-1');
    }
}
