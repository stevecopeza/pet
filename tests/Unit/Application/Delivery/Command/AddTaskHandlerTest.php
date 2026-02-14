<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Delivery\Command;

use PHPUnit\Framework\TestCase;
use Pet\Application\Delivery\Command\AddTaskCommand;
use Pet\Application\Delivery\Command\AddTaskHandler;
use Pet\Domain\Delivery\Entity\Project;
use Pet\Domain\Delivery\Repository\ProjectRepository;
use Pet\Domain\Event\EventBus;
use Pet\Domain\Delivery\Event\ProjectTaskCreated;

class AddTaskHandlerTest extends TestCase
{
    private $projectRepository;
    private $eventBus;
    private $handler;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->eventBus = $this->createMock(EventBus::class);
        $this->handler = new AddTaskHandler($this->projectRepository, $this->eventBus);
    }

    public function testHandleAddsTaskToProjectAndDispatchesEvent()
    {
        $projectId = 1;
        $command = new AddTaskCommand($projectId, 'Task 1', 5.0);

        $project = $this->createMock(Project::class);
        
        $this->projectRepository->method('findById')
            ->with($projectId)
            ->willReturn($project);

        $project->expects($this->once())
            ->method('addTask');

        $this->projectRepository->expects($this->once())
            ->method('save')
            ->with($project);

        $this->eventBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ProjectTaskCreated::class));

        $this->handler->handle($command);
    }

    public function testHandleThrowsExceptionIfProjectNotFound()
    {
        $projectId = 999;
        $command = new AddTaskCommand($projectId, 'Task 1', 5.0);

        $this->projectRepository->method('findById')
            ->with($projectId)
            ->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Project not found: 999");

        $this->handler->handle($command);
    }
}
