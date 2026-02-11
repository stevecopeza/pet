<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

use Pet\Domain\Work\Entity\KpiDefinition;
use Pet\Domain\Work\Repository\KpiDefinitionRepository;

class CreateKpiDefinitionHandler
{
    private KpiDefinitionRepository $kpiDefinitionRepository;

    public function __construct(KpiDefinitionRepository $kpiDefinitionRepository)
    {
        $this->kpiDefinitionRepository = $kpiDefinitionRepository;
    }

    public function handle(CreateKpiDefinitionCommand $command): void
    {
        $definition = new KpiDefinition(
            $command->name(),
            $command->description(),
            $command->defaultFrequency(),
            $command->unit()
        );

        $this->kpiDefinitionRepository->save($definition);
    }
}
