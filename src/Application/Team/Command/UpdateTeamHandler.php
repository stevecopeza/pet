<?php

declare(strict_types=1);

namespace Pet\Application\Team\Command;

use Pet\Domain\Team\Entity\Team;
use Pet\Domain\Team\Repository\TeamRepository;

class UpdateTeamHandler
{
    private TeamRepository $teamRepository;

    public function __construct(TeamRepository $teamRepository)
    {
        $this->teamRepository = $teamRepository;
    }

    public function handle(UpdateTeamCommand $command): void
    {
        $existingTeam = $this->teamRepository->find($command->id());

        if (!$existingTeam) {
            throw new \InvalidArgumentException("Team not found: {$command->id()}");
        }

        // Check if visual changed to increment version
        $visualChanged = (
            $existingTeam->visualType() !== $command->visualType() ||
            $existingTeam->visualRef() !== $command->visualRef()
        );
        
        $newVersion = $existingTeam->visualVersion();
        $newVisualUpdatedAt = $existingTeam->visualUpdatedAt();
        
        if ($visualChanged) {
            $newVersion++;
            $newVisualUpdatedAt = new \DateTimeImmutable();
        }

        $updatedTeam = new Team(
            $command->name(),
            $command->id(),
            $command->parentTeamId(),
            $command->managerId(),
            $command->escalationManagerId(),
            $command->status(),
            $command->visualType(),
            $command->visualRef(),
            $newVersion,
            $newVisualUpdatedAt,
            $command->memberIds(),
            $existingTeam->createdAt(),
            $existingTeam->archivedAt()
        );

        $this->teamRepository->save($updatedTeam);
    }
}
