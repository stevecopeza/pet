<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Domain\Work\Repository\WorkItemRepository;
use InvalidArgumentException;

class AssignWorkItemHandler
{
    public function __construct(
        private WorkItemRepository $repository
    ) {}

    public function handle(AssignWorkItemCommand $command): void
    {
        $workItem = $this->repository->findById($command->workItemId());

        if (!$workItem) {
            throw new InvalidArgumentException("WorkItem not found: " . $command->workItemId());
        }

        $workItem->assignUser($command->assignedUserId());
        $this->repository->save($workItem);
    }
}
