<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Domain\Work\Entity\Skill;
use Pet\Domain\Work\Repository\SkillRepository;

class UpdateSkillHandler
{
    private SkillRepository $skillRepository;

    public function __construct(SkillRepository $skillRepository)
    {
        $this->skillRepository = $skillRepository;
    }

    public function handle(UpdateSkillCommand $command): void
    {
        $existing = $this->skillRepository->findById($command->id());

        if (!$existing) {
            throw new \RuntimeException('Skill not found');
        }

        $updated = new Skill(
            $command->capabilityId(),
            $command->name(),
            $command->description(),
            $existing->id(),
            $existing->status(),
            $existing->createdAt()
        );

        $this->skillRepository->save($updated);
    }
}

