<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Domain\Work\Entity\Skill;
use Pet\Domain\Work\Repository\SkillRepository;

class CreateSkillHandler
{
    private $skillRepository;

    public function __construct(SkillRepository $skillRepository)
    {
        $this->skillRepository = $skillRepository;
    }

    public function handle(CreateSkillCommand $command): void
    {
        $skill = new Skill(
            $command->capabilityId(),
            $command->name(),
            $command->description()
        );

        $this->skillRepository->save($skill);
    }
}
