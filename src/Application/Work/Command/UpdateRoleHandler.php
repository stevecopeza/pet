<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Domain\Work\Repository\RoleRepository;

class UpdateRoleHandler
{
    private $roleRepository;

    public function __construct(RoleRepository $roleRepository)
    {
        $this->roleRepository = $roleRepository;
    }

    public function handle(UpdateRoleCommand $command): void
    {
        $role = $this->roleRepository->findById($command->id());

        if (!$role) {
            throw new \RuntimeException('Role not found');
        }

        if ($role->status() !== 'draft') {
            throw new \RuntimeException('Only draft roles can be edited');
        }

        $role->update(
            $command->name(),
            $command->level(),
            $command->description(),
            $command->successCriteria(),
            $command->requiredSkills()
        );

        $this->roleRepository->save($role);
    }
}
