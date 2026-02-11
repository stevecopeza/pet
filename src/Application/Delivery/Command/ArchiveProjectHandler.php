<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Command;

use Pet\Domain\Delivery\Repository\ProjectRepository;

class ArchiveProjectHandler
{
    private ProjectRepository $projectRepository;

    public function __construct(ProjectRepository $projectRepository)
    {
        $this->projectRepository = $projectRepository;
    }

    public function handle(ArchiveProjectCommand $command): void
    {
        $project = $this->projectRepository->findById($command->id());

        if (!$project) {
            throw new \RuntimeException('Project not found');
        }

        $project->archive();

        $this->projectRepository->save($project);
    }
}
