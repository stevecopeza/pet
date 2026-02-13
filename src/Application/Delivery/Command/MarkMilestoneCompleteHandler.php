<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Command;

use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Event\EventBus;
use Pet\Domain\Delivery\Event\MilestoneCompletedEvent;
use RuntimeException;

class MarkMilestoneCompleteHandler
{
    private ProjectRepository $projectRepository;
    private EventBus $eventBus;

    public function __construct(
        ProjectRepository $projectRepository,
        EventBus $eventBus
    ) {
        $this->projectRepository = $projectRepository;
        $this->eventBus = $eventBus;
    }

    public function handle(MarkMilestoneCompleteCommand $command): void
    {
        $project = $this->projectRepository->findById($command->projectId());
        if (!$project) {
            throw new RuntimeException("Project not found: " . $command->projectId());
        }

        $project->completeTask($command->milestoneTitle());

        $this->projectRepository->save($project);

        $this->eventBus->dispatch(new MilestoneCompletedEvent(
            $project->id(),
            $command->milestoneTitle()
        ));
    }
}
