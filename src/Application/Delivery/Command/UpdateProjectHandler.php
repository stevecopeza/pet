<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Command;

use Pet\Domain\Delivery\Repository\ProjectRepository;

class UpdateProjectHandler
{
    private ProjectRepository $projectRepository;

    public function __construct(ProjectRepository $projectRepository)
    {
        $this->projectRepository = $projectRepository;
    }

    public function handle(UpdateProjectCommand $command): void
    {
        $project = $this->projectRepository->findById($command->id());

        if (!$project) {
            throw new \RuntimeException('Project not found');
        }

        $project->update(
            $command->name(),
            $command->status(),
            $command->startDate(),
            $command->endDate(),
            $command->malleableData()
        );

        $this->projectRepository->save($project);
    }
}
