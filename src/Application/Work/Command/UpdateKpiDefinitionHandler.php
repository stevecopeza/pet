<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Domain\Work\Entity\KpiDefinition;
use Pet\Domain\Work\Repository\KpiDefinitionRepository;

class UpdateKpiDefinitionHandler
{
    private KpiDefinitionRepository $kpiDefinitionRepository;

    public function __construct(KpiDefinitionRepository $kpiDefinitionRepository)
    {
        $this->kpiDefinitionRepository = $kpiDefinitionRepository;
    }

    public function handle(UpdateKpiDefinitionCommand $command): void
    {
        $existing = $this->kpiDefinitionRepository->findById($command->id());

        if (!$existing) {
            throw new \RuntimeException('KPI Definition not found');
        }

        $updated = new KpiDefinition(
            $command->name(),
            $command->description(),
            $command->defaultFrequency(),
            $command->unit(),
            $existing->id(),
            $existing->createdAt()
        );

        $this->kpiDefinitionRepository->save($updated);
    }
}

