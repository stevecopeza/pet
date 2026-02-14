<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Command;

use Pet\Domain\Delivery\Entity\Task;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Event\EventBus;
use Pet\Domain\Delivery\Event\ProjectTaskCreated;

class AddTaskHandler
{
    private ProjectRepository $projectRepository;
    private EventBus $eventBus;

    public function __construct(ProjectRepository $projectRepository, EventBus $eventBus)
    {
        $this->projectRepository = $projectRepository;
        $this->eventBus = $eventBus;
    }

    public function handle(AddTaskCommand $command): void
    {
        $project = $this->projectRepository->findById($command->projectId());
        if (!$project) {
            throw new \DomainException("Project not found: {$command->projectId()}");
        }

        $task = new Task(
            $command->name(),
            $command->estimatedHours()
        );

        $project->addTask($task);

        $this->projectRepository->save($project);

        $this->eventBus->dispatch(new ProjectTaskCreated($project, $task));
    }
}
