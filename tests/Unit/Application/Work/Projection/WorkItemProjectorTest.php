<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Work\Projection;

use PHPUnit\Framework\TestCase;
use Pet\Application\Work\Projection\WorkItemProjector;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Work\Repository\DepartmentQueueRepository;
use Pet\Domain\Work\Service\DepartmentResolver;
use Pet\Domain\Work\Service\SlaClockCalculator;
use Pet\Domain\Identity\Repository\CustomerRepository;
use Pet\Domain\Delivery\Event\ProjectTaskCreated;
use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Delivery\Entity\Task;
use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Work\Entity\DepartmentQueue;

class WorkItemProjectorTest extends TestCase
{
    private $workItemRepository;
    private $departmentQueueRepository;
    private $departmentResolver;
    private $slaClockCalculator;
    private $customerRepository;
    private $projector;

    protected function setUp(): void
    {
        $this->workItemRepository = $this->createMock(WorkItemRepository::class);
        $this->departmentQueueRepository = $this->createMock(DepartmentQueueRepository::class);
        $this->departmentResolver = $this->createMock(DepartmentResolver::class);
        $this->slaClockCalculator = $this->createMock(SlaClockCalculator::class);
        $this->customerRepository = $this->createMock(CustomerRepository::class);
        $featureFlags = $this->createMock(\Pet\Application\System\Service\FeatureFlagService::class);
        $featureFlags->method('isWorkProjectionEnabled')->willReturn(true);

        $this->projector = new WorkItemProjector(
            $this->workItemRepository,
            $this->departmentQueueRepository,
            $this->departmentResolver,
            $this->slaClockCalculator,
            $this->customerRepository,
            $featureFlags
        );
    }

    public function testOnProjectTaskCreatedDoesNotCreateWorkItem(): void
    {
        $project = $this->createMock(Project::class);
        $task = $this->createMock(Task::class);

        $event = new ProjectTaskCreated($project, $task);

        $this->workItemRepository->expects($this->never())
            ->method('save');

        $this->departmentQueueRepository->expects($this->never())
            ->method('save');

        $this->projector->onProjectTaskCreated($event);
    }
}
