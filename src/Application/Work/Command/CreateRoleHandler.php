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
        /** @var \wpdb|null $wpdb */
        global $wpdb;

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

        $id = $role->id();
        if ($id !== null && $id > 0) {
            return $id;
        }

        if ($wpdb instanceof \wpdb && isset($wpdb->insert_id) && (int)$wpdb->insert_id > 0) {
            return (int)$wpdb->insert_id;
        }

        throw new \RuntimeException('Failed to persist Role and obtain identifier.');
    }
}
