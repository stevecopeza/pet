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

        $this->projector = new WorkItemProjector(
            $this->workItemRepository,
            $this->departmentQueueRepository,
            $this->departmentResolver,
            $this->slaClockCalculator,
            $this->customerRepository
        );
    }

    public function testOnProjectTaskCreatedWithRoleId()
    {
        $project = $this->createMock(Project::class);
        $project->method('customerId')->willReturn(1);
        $project->method('endDate')->willReturn(new \DateTimeImmutable());
        $project->method('soldValue')->willReturn(1000.0);

        $task = $this->createMock(Task::class);
        $task->method('id')->willReturn(101);
        $task->method('roleId')->willReturn(55); // Role ID present

        $event = new ProjectTaskCreated($project, $task);

        $this->departmentResolver->method('resolveForProjectTask')
            ->willReturn('dept-delivery');

        // Expect WorkItem to be saved with required_role_id = 55
        $this->workItemRepository->expects($this->exactly(2))
            ->method('save')
            ->with($this->callback(function (WorkItem $item) {
                // Verify Role ID
                return $item->getRequiredRoleId() === 55;
            }));

        $this->projector->onProjectTaskCreated($event);
    }
}
