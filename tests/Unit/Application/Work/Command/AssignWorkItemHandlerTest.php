<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Work\Command;

use Pet\Application\Work\Command\AssignWorkItemCommand;
use Pet\Application\Work\Command\AssignWorkItemHandler;
use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Work\Repository\WorkItemRepository;
use PHPUnit\Framework\TestCase;

class AssignWorkItemHandlerTest extends TestCase
{
    private $repository;
    private $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(WorkItemRepository::class);
        $this->handler = new AssignWorkItemHandler($this->repository);
    }

    public function testAssignsUserToWorkItem()
    {
        $workItemId = 'item-1';
        $userId = 'user-1';
        $command = new AssignWorkItemCommand($workItemId, $userId);

        $workItem = $this->createMock(WorkItem::class);
        $this->repository->method('findById')->with($workItemId)->willReturn($workItem);

        $workItem->expects($this->once())->method('assignUser')->with($userId);
        $this->repository->expects($this->once())->method('save')->with($workItem);

        $this->handler->handle($command);
    }

    public function testThrowsExceptionIfWorkItemNotFound()
    {
        $this->repository->method('findById')->willReturn(null);
        $this->expectException(\InvalidArgumentException::class);
        
        $command = new AssignWorkItemCommand('invalid-id', 'user-1');
        $this->handler->handle($command);
    }
}
