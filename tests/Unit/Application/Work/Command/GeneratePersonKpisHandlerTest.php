<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Work\Command;

use Pet\Application\Work\Command\GeneratePersonKpisCommand;
use Pet\Application\Work\Command\GeneratePersonKpisHandler;
use Pet\Domain\Work\Entity\RoleKpi;
use Pet\Domain\Work\Repository\PersonKpiRepository;
use Pet\Domain\Work\Repository\RoleKpiRepository;
use PHPUnit\Framework\TestCase;

class GeneratePersonKpisHandlerTest extends TestCase
{
    public function testHandleGeneratesPersonKpisFromRoleKpis(): void
    {
        // Mock repositories
        $personKpiRepository = $this->createMock(PersonKpiRepository::class);
        $roleKpiRepository = $this->createMock(RoleKpiRepository::class);

        // Setup Role KPIs
        $roleKpi = $this->createMock(RoleKpi::class);
        $roleKpi->method('kpiDefinitionId')->willReturn(101);
        $roleKpi->method('targetValue')->willReturn(95.5);

        $roleKpiRepository->method('findByRoleId')
            ->with(5)
            ->willReturn([$roleKpi]);

        // Expect save to be called
        $personKpiRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function ($personKpi) {
                return $personKpi->employeeId() === 1
                    && $personKpi->kpiDefinitionId() === 101
                    && $personKpi->roleId() === 5
                    && $personKpi->targetValue() === 95.5
                    && $personKpi->periodStart()->format('Y-m-d') === '2023-01-01'
                    && $personKpi->periodEnd()->format('Y-m-d') === '2023-03-31';
            }));

        // Execute handler
        $handler = new GeneratePersonKpisHandler($personKpiRepository, $roleKpiRepository);
        
        $command = new GeneratePersonKpisCommand(
            1, // employeeId
            5, // roleId
            '2023-01-01',
            '2023-03-31'
        );

        $handler->handle($command);
    }
}
