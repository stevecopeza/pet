<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Work\Command;

use Pet\Application\Work\Command\OverrideWorkItemPriorityCommand;
use Pet\Application\Work\Command\OverrideWorkItemPriorityHandler;
use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Work\Service\PriorityScoringService;
use PHPUnit\Framework\TestCase;

class OverrideWorkItemPriorityHandlerTest extends TestCase
{
    private $repository;
    private $scoringService;
    private $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(WorkItemRepository::class);
        $this->scoringService = $this->createMock(PriorityScoringService::class);
        $this->handler = new OverrideWorkItemPriorityHandler($this->repository, $this->scoringService);
    }

    public function testOverridesPriorityAndRecalculatesScore()
    {
        $workItemId = 'item-1';
        $overrideValue = 100.0;
        $command = new OverrideWorkItemPriorityCommand($workItemId, $overrideValue);

        $workItem = $this->createMock(WorkItem::class);
        $this->repository->method('findById')->with($workItemId)->willReturn($workItem);

        $workItem->expects($this->once())->method('setManagerPriorityOverride')->with($overrideValue);
        
        $this->scoringService->expects($this->once())->method('calculate')->with($workItem)->willReturn(150.0);
        $workItem->expects($this->once())->method('updatePriorityScore')->with(150.0);
        
        $this->repository->expects($this->once())->method('save')->with($workItem);

        $this->handler->handle($command);
    }

    public function testThrowsExceptionIfWorkItemNotFound()
    {
        $this->repository->method('findById')->willReturn(null);
        $this->expectException(\InvalidArgumentException::class);
        
        $command = new OverrideWorkItemPriorityCommand('invalid-id', 100.0);
        $this->handler->handle($command);
    }
}
