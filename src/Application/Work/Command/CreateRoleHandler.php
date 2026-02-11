<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Domain\Work\Entity\Role;
use Pet\Domain\Work\Repository\RoleRepository;

class CreateRoleHandler
{
    private $roleRepository;

    public function __construct(RoleRepository $roleRepository)
    {
        $this->roleRepository = $roleRepository;
    }

    public function handle(CreateRoleCommand $command): int
    {
        $role = new Role(
            $command->name(),
            $command->level(),
            $command->description(),
            $command->successCriteria(),
            null, // id
            1,    // version
            'draft',
            $command->requiredSkills()
        );

        $this->roleRepository->save($role);

        return $role->id();
    }
}
