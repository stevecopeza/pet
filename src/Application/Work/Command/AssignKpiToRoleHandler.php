<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Domain\Work\Entity\RoleKpi;
use Pet\Domain\Work\Repository\RoleKpiRepository;

class AssignKpiToRoleHandler
{
    private RoleKpiRepository $roleKpiRepository;

    public function __construct(RoleKpiRepository $roleKpiRepository)
    {
        $this->roleKpiRepository = $roleKpiRepository;
    }

    public function handle(AssignKpiToRoleCommand $command): void
    {
        $roleKpi = new RoleKpi(
            $command->roleId(),
            $command->kpiDefinitionId(),
            $command->weightPercentage(),
            $command->targetValue(),
            $command->measurementFrequency()
        );

        $this->roleKpiRepository->save($roleKpi);
    }
}
