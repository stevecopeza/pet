<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Domain\Work\Repository\RoleRepository;

class PublishRoleHandler
{
    private $roleRepository;

    public function __construct(RoleRepository $roleRepository)
    {
        $this->roleRepository = $roleRepository;
    }

    public function handle(PublishRoleCommand $command): void
    {
        $role = $this->roleRepository->findById($command->roleId());

        if (!$role) {
            throw new \InvalidArgumentException('Role not found.');
        }

        $role->publish();
        $this->roleRepository->save($role);
    }
}
