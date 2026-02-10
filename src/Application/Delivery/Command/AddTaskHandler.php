<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Command;

use Pet\Domain\Delivery\Entity\Task;
use Pet\Domain\Delivery\Repository\ProjectRepository;

class AddTaskHandler
{
    private ProjectRepository $projectRepository;

    public function __construct(ProjectRepository $projectRepository)
    {
        $this->projectRepository = $projectRepository;
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
    }
}
