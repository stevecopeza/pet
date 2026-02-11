<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Work\Command;

use Pet\Application\Work\Command\RateEmployeeSkillCommand;
use Pet\Application\Work\Command\RateEmployeeSkillHandler;
use Pet\Domain\Work\Repository\PersonSkillRepository;
use PHPUnit\Framework\TestCase;

class RateEmployeeSkillHandlerTest extends TestCase
{
    public function testHandleSavesPersonSkillRating(): void
    {
        // Mock repository
        $personSkillRepository = $this->createMock(PersonSkillRepository::class);

        // Expect save to be called
        $personSkillRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function ($personSkill) {
                return $personSkill->employeeId() === 1
                    && $personSkill->skillId() === 20
                    && $personSkill->selfRating() === 4
                    && $personSkill->managerRating() === 3
                    && $personSkill->effectiveDate()->format('Y-m-d') === '2023-06-01'
                    && $personSkill->reviewCycleId() === 10;
            }));

        // Execute handler
        $handler = new RateEmployeeSkillHandler($personSkillRepository);
        
        $command = new RateEmployeeSkillCommand(
            1, // employeeId
            20, // skillId
            4, // selfRating
            3, // managerRating
            '2023-06-01', // effectiveDate
            10 // reviewCycleId
        );

        $handler->handle($command);
    }
}
