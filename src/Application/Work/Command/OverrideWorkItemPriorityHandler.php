<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Work\Service\PriorityScoringService;
use InvalidArgumentException;

class OverrideWorkItemPriorityHandler
{
    public function __construct(
        private WorkItemRepository $repository,
        private PriorityScoringService $scoringService
    ) {}

    public function handle(OverrideWorkItemPriorityCommand $command): void
    {
        $workItem = $this->repository->findById($command->workItemId());

        if (!$workItem) {
            throw new InvalidArgumentException("WorkItem not found: " . $command->workItemId());
        }

        $workItem->setManagerPriorityOverride($command->overrideValue());
        
        // Recalculate score immediately
        $newScore = $this->scoringService->calculate($workItem);
        $workItem->updatePriorityScore($newScore);

        $this->repository->save($workItem);
    }
}
